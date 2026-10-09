<?php
/**
 * WhatsApp Attendance Service for OrbitSend & AI Integration
 * Handles incoming WhatsApp messages from group/direct chats,
 * parses attendance actions (Check-in, Check-out, Early leave, etc.) with AI & NLP,
 * matches employees by phone number/employee code/name, and records attendance in daily_sheets.
 */

require_once __DIR__ . '/../config/database.php';

class WhatsAppAttendanceService {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }

    /**
     * Get Settings merged from Database and .env Configuration
     */
    public function getSettings() {
        $stmt = $this->pdo->query("SELECT * FROM whatsapp_attendance_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Resolve config: database values take precedence or fallback to .env
        $apiUrl = $settings['api_url'] ?? (getenv('ORBITSEND_API_URL') ?: 'https://app.orbitsend.com/api/send/whatsapp');
        $apiSecret = $settings['api_secret'] ?? (getenv('ORBITSEND_SECRET') ?: '');
        $webhookToken = $settings['webhook_token'] ?? (getenv('ORBITSEND_WEBHOOK_SECRET') ?: '');
        $uniqueId = $settings['unique_id'] ?? (getenv('ORBITSEND_ACCOUNT') ?: '');
        $aiProvider = $settings['ai_provider'] ?? (getenv('AI_PROVIDER') ?: 'auto');
        $aiApiKey = $settings['ai_api_key'] ?? (getenv('AI_API_KEY') ?: '');

        return [
            'id' => 1,
            'api_url' => $apiUrl,
            'api_secret' => $apiSecret,
            'webhook_token' => $webhookToken,
            'unique_id' => $uniqueId,
            'account' => $uniqueId,
            'group_jid' => $settings['group_jid'] ?? '',
            'ai_provider' => $aiProvider,
            'ai_api_key' => $aiApiKey,
            'is_enabled' => isset($settings['is_enabled']) ? (int)$settings['is_enabled'] : 1,
            'auto_reply' => isset($settings['auto_reply']) ? (int)$settings['auto_reply'] : 0,
            'default_shift_start' => $settings['default_shift_start'] ?? '09:00 AM',
            'default_shift_end' => $settings['default_shift_end'] ?? '06:00 PM'
        ];
    }

    /**
     * Process Incoming Message Payload
     * Supports OrbitSend webhook format and standard WhatsApp gateway payloads.
     */
    public function processIncomingMessage($payload) {
        $settings = $this->getSettings();
        if (empty($settings['is_enabled'])) {
            return [
                'success' => false,
                'message' => 'WhatsApp Attendance integration is currently disabled in settings.'
            ];
        }

        // Extract message fields from various OrbitSend/WhatsApp payload structures
        $messageData = $this->extractPayloadData($payload);
        if (!$messageData || empty($messageData['text'])) {
            return [
                'success' => false,
                'message' => 'No readable text message found in webhook payload.'
            ];
        }

        $rawText = trim($messageData['text']);
        $senderPhone = $this->cleanPhoneNumber($messageData['sender_phone'] ?? '');
        $senderName = trim($messageData['sender_name'] ?? '');
        $groupJid = trim($messageData['group_jid'] ?? '');
        $messageId = trim($messageData['message_id'] ?? '');
        $timestamp = !empty($messageData['timestamp']) ? (int)$messageData['timestamp'] : time();

        // Optional Group Filter
        if (!empty($settings['group_jid'])) {
            $expectedDigits = preg_replace('/[^\d]/', '', $settings['group_jid']);
            $receivedDigits = preg_replace('/[^\d]/', '', $groupJid);

            // If incoming message was from a group, verify it matches the configured group
            if (!empty($groupJid) && !empty($expectedDigits)) {
                if ($expectedDigits !== $receivedDigits && stripos($groupJid, $settings['group_jid']) === false && stripos($settings['group_jid'], $groupJid) === false) {
                    // Record ignored group log so HR has full visibility
                    $stmtLog = $this->pdo->prepare("
                        INSERT INTO whatsapp_attendance_logs 
                            (message_id, sender_phone, sender_name, group_jid, employee_id, raw_message, parsed_action, extracted_time, action_date, sheet_id, status, remarks)
                        VALUES 
                            (?, ?, ?, ?, NULL, ?, 'unknown', ?, ?, NULL, 'ignored', ?)
                    ");
                    $stmtLog->execute([
                        $messageId,
                        $senderPhone,
                        $senderName,
                        $groupJid,
                        $rawText,
                        date('h:i A', $timestamp),
                        date('Y-m-d', $timestamp),
                        "Message ignored: Received from group '{$groupJid}' which does not match configured group '{$settings['group_jid']}'."
                    ]);

                    return [
                        'success' => false,
                        'message' => "Message ignored: From non-configured group ({$groupJid})."
                    ];
                }
            }
        }

        // Parse attendance action and time from text (with AI & NLP engine)
        $parsed = $this->parseAttendanceText($rawText, $timestamp, $settings);
        $action = $parsed['action'];
        $extractedTime = $parsed['formatted_time'];
        $actionDate = date('Y-m-d', $timestamp);
        $aiUsed = !empty($parsed['ai_used']);

        // Match Employee by Phone, Employee Code, or Push Name
        $employee = $this->findEmployee($senderPhone, $senderName, $rawText);
        $empId = $employee ? (int)$employee['id'] : null;

        // If employee was matched but senderPhone was blank (e.g. from group message), populate from employee profile
        if ($employee) {
            if (empty($senderPhone)) {
                $senderPhone = $employee['profile_phone'] ?? ($employee['phone'] ?? ($employee['whatsapp_number'] ?? ''));
            }
            if (empty($senderName)) {
                $senderName = $employee['name'] ?? '';
            }
        }

        $logStatus = 'applied';
        $remarks = '';
        $sheetId = null;

        if (!$employee) {
            $logStatus = 'unmatched';
            $remarks = "Sender not recognized (Phone: '{$senderPhone}', Name: '{$senderName}'). Please map phone number in Employee Profile.";
        } elseif ($action === 'unknown') {
            $logStatus = 'ignored';
            $remarks = "Message did not match attendance keywords (In/Out/Leave). Message: '{$rawText}'";
        } else {
            // Apply attendance action to daily_sheets
            $applyResult = $this->applyToDailySheet($employee, $action, $extractedTime, $actionDate, $rawText, $parsed);
            $sheetId = $applyResult['sheet_id'] ?? null;
            $remarks = $applyResult['remarks'] ?? '';
            $logStatus = $applyResult['status'] ?? 'applied';
            if ($aiUsed) {
                $remarks .= " [AI Assisted]";
            }
        }

        // Record in whatsapp_attendance_logs
        $stmtLog = $this->pdo->prepare("
            INSERT INTO whatsapp_attendance_logs 
                (message_id, sender_phone, sender_name, group_jid, employee_id, raw_message, parsed_action, extracted_time, action_date, sheet_id, status, remarks)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $messageId,
            $senderPhone,
            $senderName,
            $groupJid,
            $empId,
            $rawText,
            $action,
            $extractedTime,
            $actionDate,
            $sheetId,
            $logStatus,
            $remarks
        ]);

        $logId = $this->pdo->lastInsertId();

        // Send Auto-Reply if enabled (Default: 0 - disabled per user preference)
        if (!empty($settings['auto_reply']) && $employee && $action !== 'unknown') {
            $replyMsg = $this->generateReplyMessage($employee['name'], $action, $extractedTime);
            $this->sendWhatsAppMessage($settings, $groupJid ?: $senderPhone, $replyMsg);
        }

        return [
            'success' => true,
            'log_id' => $logId,
            'action' => $action,
            'extracted_time' => $extractedTime,
            'employee' => $employee ? $employee['name'] : 'Unmatched',
            'emp_code' => $employee ? ($employee['emp_code'] ?? '') : '',
            'status' => $logStatus,
            'remarks' => $remarks
        ];
    }

    /**
     * Extract Data from various OrbitSend/Baileys Webhook Formats
     */
    private function extractPayloadData($payload) {
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        // Save last incoming payload for debugging and verification
        @file_put_contents(__DIR__ . '/../scratch/last_webhook.json', json_encode($payload, JSON_PRETTY_PRINT));

        $data = $payload['data'] ?? $payload;
        if (isset($data['messages']) && is_array($data['messages'])) {
            $data = $data['messages'][0] ?? $data;
        }

        $key = $data['key'] ?? ($payload['key'] ?? []);

        // 1. Detect Group JID / ID across all known OrbitSend / Baileys attributes
        $groupJid = '';
        $potentialGroupFields = [
            $data['group_id'] ?? null,
            $data['group_jid'] ?? null,
            $data['group'] ?? null,
            $data['chat_id'] ?? null,
            $data['chat'] ?? null,
            $key['remoteJid'] ?? null,
            $data['remoteJid'] ?? null,
            $data['from'] ?? null,
            $data['recipient'] ?? null,
            $payload['group_id'] ?? null,
            $payload['group_jid'] ?? null,
            $payload['group'] ?? null,
            $payload['remoteJid'] ?? null
        ];

        foreach ($potentialGroupFields as $candidate) {
            if ($candidate && is_string($candidate) && (stripos($candidate, '@g.us') !== false || preg_match('/^\d{15,22}$/', $candidate))) {
                $groupJid = trim($candidate);
                break;
            }
        }

        // 2. Detect Sender Phone Number (ensuring it is NOT the group JID itself)
        $senderPhone = '';
        $potentialSenderFields = [
            $key['participant'] ?? null,
            $data['participant'] ?? null,
            $payload['participant'] ?? null,
            $data['sender'] ?? null,
            $data['phone'] ?? null,
            $data['mobile'] ?? null,
            $data['from'] ?? null,
            $payload['sender'] ?? null,
            $payload['phone'] ?? null,
            $payload['from'] ?? null,
            $data['recipient'] ?? null,
            $payload['recipient'] ?? null
        ];

        foreach ($potentialSenderFields as $candidate) {
            if ($candidate && is_string($candidate)) {
                $trimmed = trim($candidate);
                // Skip if this candidate is actually a group address
                if (stripos($trimmed, '@g.us') !== false) {
                    continue;
                }
                $clean = $this->cleanPhoneNumber($trimmed);
                if (!empty($clean) && strlen($clean) >= 10 && strlen($clean) <= 15) {
                    $senderPhone = $trimmed;
                    break;
                }
            }
        }

        // 3. Extract Message Text
        $msgObj = $data['message'] ?? ($data['msg'] ?? ($payload['message'] ?? []));
        $text = '';
        if (is_string($msgObj)) {
            $text = $msgObj;
        } elseif (is_array($msgObj)) {
            $text = $msgObj['conversation'] 
                 ?? ($msgObj['extendedTextMessage']['text'] 
                 ?? ($msgObj['imageMessage']['caption'] 
                 ?? ($msgObj['videoMessage']['caption'] 
                 ?? ($msgObj['text'] ?? ''))));
        }

        // 4. Extract Push Name / Sender Name
        $pushName = $data['pushName'] 
                 ?? ($data['sender_name'] 
                 ?? ($payload['pushName'] 
                 ?? ($data['name'] 
                 ?? ($data['contact_name'] 
                 ?? ($payload['sender_name']
                 ?? ($payload['name'] ?? ''))))));

        // 5. Message ID and Timestamp
        $msgId = $key['id'] ?? ($data['id'] ?? ($payload['message_id'] ?? ($payload['id'] ?? uniqid('wa_'))));
        $ts = $data['messageTimestamp'] ?? ($data['created'] ?? ($payload['timestamp'] ?? time()));
        if (strlen((string)$ts) > 10) {
            $ts = (int)($ts / 1000);
        }

        return [
            'text' => $text,
            'sender_phone' => $senderPhone,
            'sender_name' => is_string($pushName) ? $pushName : '',
            'group_jid' => $groupJid,
            'message_id' => (string)$msgId,
            'timestamp' => (int)$ts
        ];
    }

    /**
     * Clean and Normalize Phone Numbers
     */
    public function cleanPhoneNumber($phone) {
        if (!$phone) return '';
        // Remove domain parts like @s.whatsapp.net or @g.us or :1
        $phone = preg_replace('/@[a-zA-Z0-9\.\_\-]+/', '', $phone);
        $phone = preg_replace('/:\d+/', '', $phone);
        // Strip everything except digits
        $digits = preg_replace('/[^\d]/', '', $phone);

        // Normalize Pakistani phone numbers (923001234567 <-> 03001234567)
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '92') {
            $digits = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && substr($digits, 0, 1) === '3') {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * Intelligent Attendance Parser (NLP + AI Fallback)
     */
    public function parseAttendanceText($text, $msgTimestamp = null, $settings = null) {
        $msgTimestamp = $msgTimestamp ?: time();
        $raw = trim($text);
        $clean = strtolower($raw);

        // 1. Fast Noise / Greeting Filter (Safely Ignore non-attendance chat)
        if (preg_match('/^(good\s*morning|gm|good\s*afternoon|good\s*evening|happy\s*birthday|hbd|congratulations|congrats|mubarak|ameen|jazakallah|shukriya|thanks|thank\s*you|ok|okay|k|done|ji|jee|yes|no|salam|wsalam|assalam|a\.o\.a|aoa|hi|hello)\.?$/i', $clean) ||
            preg_match('/^(https?:\/\/[^\s]+)$/i', $clean)) {
            return [
                'action' => 'unknown',
                'formatted_time' => date('h:i A', $msgTimestamp),
                'raw_text' => $raw,
                'ai_used' => false,
                'reason' => 'Chat noise / greeting'
            ];
        }

        // 2. Extract explicit time if present: "4:46", "4:46 pm", "09:00 am", "at 4.46", "at 5 pm", "4 baje"
        $extractedTime = null;
        $matchedHour = null;
        $matchedMin = null;
        $matchedMeridiem = null;

        if (preg_match('/\b(\d{1,2})[:.](\d{2})\s*(am|pm)?\b/i', $clean, $m)) {
            $matchedHour = (int)$m[1];
            $matchedMin = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $matchedMeridiem = !empty($m[3]) ? strtoupper($m[3]) : null;
        } elseif (preg_match('/\b(?:at|@)\s*(\d{1,2})\s*(am|pm)\b/i', $clean, $m)) {
            $matchedHour = (int)$m[1];
            $matchedMin = '00';
            $matchedMeridiem = strtoupper($m[2]);
        } elseif (preg_match('/\b(\d{1,2})\s*(?:baje|bajay|o\'clock)\b/i', $clean, $m)) {
            $matchedHour = (int)$m[1];
            $matchedMin = '00';
        }

        if ($matchedHour !== null) {
            if ($matchedMeridiem) {
                if ($matchedMeridiem === 'PM' && $matchedHour < 12) $matchedHour += 12;
                if ($matchedMeridiem === 'AM' && $matchedHour === 12) $matchedHour = 0;
            } else {
                $currentHour = (int)date('H', $msgTimestamp);
                if ($matchedHour <= 6 && $currentHour >= 12) {
                    $matchedHour += 12;
                }
            }
            $formattedTime = date('h:i A', strtotime(sprintf('%02d:%s:00', $matchedHour, $matchedMin)));
        } else {
            $formattedTime = date('h:i A', $msgTimestamp);
        }

        // 3. Check if AI should be used for long conversational / complex Roman Urdu messages
        $settings = $settings ?: $this->getSettings();
        $wordCount = str_word_count($clean);
        $hasComplexSignals = ($wordCount > 4 || stripos($clean, 'unable') !== false || stripos($clean, 'emergency') !== false || stripos($clean, 'leave') !== false || stripos($clean, 'going to') !== false);
        
        if ($hasComplexSignals && !empty($settings['ai_api_key'])) {
            $aiRes = $this->callAiParser($raw, $msgTimestamp, $settings);
            if ($aiRes && $aiRes['action'] !== 'unknown') {
                return $aiRes;
            }
        }

        // 4. Deterministic & High-Precision NLP Rule Engine
        $action = 'unknown';
        $meta = [];

        // Rule A: Field Visit / Outdoor Shoot / Meeting (Keeps shift ACTIVE)
        if (preg_match('/\b(going\s*to|visit\s*to|on\s*visit|location\s*shoot|shoot\s*at|outdoor\s*shoot|coverage\s*at|organic\s*village|lahore\s*village|club|gymkhana|press\s*club|court|high\s*court|studio\s*shoot|meeting\s*at|field\s*visit)\b/i', $clean)) {
            $action = 'field_visit';
            $meta['visit_location'] = $raw;
        }
        // Rule B: Return from Field Visit / Errand (Keeps shift ACTIVE)
        elseif (preg_match('/\b(back\s*in\s*office|back\s*at\s*office|back\s*at\s*desk|returned|wapis\s*aa\s*gaya|wapis\s*agaya|wapis\s*a\s*gaya|^back\.?$|^back\s*now\.?$)\b/i', $clean)) {
            $action = 'back_in_office';
        }
        // Rule C: Full Day Leave Notice / Unable to attend
        elseif (preg_match('/\b(unable\s*to\s*join|can\'?t\s*join|cannot\s*join|not\s*coming|nahi\s*aa\s*sakta|nahi\s*a\s*sakta|on\s*leave|alternate\s*leave|casual\s*leave|sick\s*leave|medical\s*leave|urgent\s*work|emergency\s*at\s*home|emergency\s*leave|today\s*i\s*am\s*on\s*leave|chutti\s*hai|chutti\s*per|chutti\s*par)\b/i', $clean)) {
            $action = 'leave_notice';
            $meta['leave_type'] = (stripos($clean, 'alternate') !== false) ? 'alternate' : ((stripos($clean, 'sick') !== false || stripos($clean, 'medical') !== false || stripos($clean, 'tabiyat') !== false) ? 'sick' : 'emergency');
            $meta['leave_reason'] = $raw;
            if (preg_match('/informed\s+([a-zA-Z\s\.]+)/i', $raw, $infM)) {
                $meta['informed_person'] = trim($infM[1]);
            }
        }
        // Rule D: Early Leaving / Short Leave (Partial shift)
        elseif (preg_match('/\b(leaving\s*early|early\s*leave|early\s*out|short\s*leave|half\s*day|leaving\s*for\s*home\s*early|out\s*early|jaldi\s*chutti|emergency\s*exit)\b/i', $clean)) {
            $action = 'leaving_early';
            $meta['leave_type'] = 'short_leave';
            $meta['leave_reason'] = $raw;
        }
        // Rule E: Check-In
        elseif (preg_match('/\b(in\b|check\s*in|checked\s*in|reach\s*office|reached|reach|arrived|present|hazir|on\s*duty|login|joined|aa\s*gaya|a\s*gaya|agaya|pohanch|pohnch|ponch|pohench|office\s*in|duty\s*start|working\s*start|^in\b)\b/i', $clean)) {
            $action = 'check_in';
        }
        // Rule F: Check-Out / Shift End
        elseif (preg_match('/\b(out\b|check\s*out|checked\s*out|leaving\b|off\s*duty|shift\s*over|day\s*end|done\s*for\s*today|logout|going\s*home|bye|nikal\s*raha|nikal\s*gaya|duty\s*off|duty\s*end|closing|punch\s*out|^out\b)\b/i', $clean)) {
            $action = 'check_out';
        }

        return array_merge([
            'action' => $action,
            'formatted_time' => $formattedTime,
            'raw_text' => $raw,
            'ai_used' => false
        ], $meta);
    }

    /**
     * AI Natural Language Parser (Supports OpenAI gpt-4o-mini & Google Gemini Flash)
     */
    private function callAiParser($text, $msgTimestamp, $settings) {
        $apiKey = $settings['ai_api_key'] ?? '';
        if (!$apiKey) return null;

        $currentTimeStr = date('h:i A', $msgTimestamp);
        $prompt = "You are an AI Attendance & HR Assistant for Discover Pakistan TV (a media & broadcasting company in Lahore, Pakistan).
Analyze this WhatsApp message sent by an employee in the office group:
\"{$text}\"

Classify the intent into EXACTLY ONE of these actions:
1. \"check_in\": Arriving at office / starting shift (e.g. \"in\", \"in at 9:30 am\", \"reached\", \"present\", \"aa gaya\").
2. \"check_out\": Leaving office at end of day / finishing shift (e.g. \"out\", \"out at 6:00\", \"leaving for home\", \"shift done\").
3. \"leaving_early\": Leaving earlier than normal shift due to emergency/doctor (e.g. \"leaving early at 4pm due to emergency\").
4. \"field_visit\": Employee going out TEMPORARILY for outdoor shoot, assignment, meeting, coverage, or errand (e.g. \"Going to Lahore Organic Village\", \"Going to Club\", \"Outdoor shoot at Gulberg\", \"Press club coverage\"). NOTE: This is NOT check-out!
5. \"back_in_office\": Employee returning to office from outdoor visit or lunch (e.g. \"back\", \"back in office\", \"returned\", \"wapis agaya\").
6. \"leave_notice\": Full-day leave notice or inability to join (e.g. \"Respected HR unable to join today due to emergency at home already informed HOD\", \"On alternate leave against 6-9-2026\", \"Urgent work at home\").
7. \"unknown\": Greetings, unrelated chatter, links, stickers, congratulations (e.g. \"Good morning\", \"Happy birthday\", \"Ok\", \"Done\").

Extract:
- action: one of the 7 exact action keys above
- formatted_time: time mentioned (or default to \"{$currentTimeStr}\")
- leave_type: if leave or early leave (\"emergency\", \"alternate\", \"casual\", \"sick\", \"official\", \"short_leave\")
- leave_reason: clean summary of reason if mentioned
- informed_person: person informed if mentioned (e.g. \"HOD\", \"Director HR\", \"Sir Ghulam Abbas\")
- visit_location: location if field_visit (e.g. \"Lahore Organic Village\", \"Club\")

Output strictly valid JSON only:
{
  \"action\": \"check_in|check_out|leaving_early|field_visit|back_in_office|leave_notice|unknown\",
  \"formatted_time\": \"hh:mm AM/PM\",
  \"leave_type\": \"...\",
  \"leave_reason\": \"...\",
  \"informed_person\": \"...\",
  \"visit_location\": \"...\"
}";

        try {
            $provider = strtolower($settings['ai_provider'] ?? 'auto');
            $aiResponseText = '';

            if ($provider === 'openai' || ($provider === 'auto' && strpos($apiKey, 'sk-') === 0)) {
                // OpenAI API with gpt-4o-mini (state of the art lightweight multilingual)
                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a precise JSON-only attendance parsing assistant.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                $res = curl_exec($ch);
                curl_close($ch);

                if ($res) {
                    $json = json_decode($res, true);
                    $aiResponseText = $json['choices'][0]['message']['content'] ?? '';
                }
            } else {
                // Google Gemini API (gemini-1.5-flash)
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json'
                    ]
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                $res = curl_exec($ch);
                curl_close($ch);

                if ($res) {
                    $json = json_decode($res, true);
                    $aiResponseText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                }
            }

            if (!empty($aiResponseText)) {
                if (preg_match('/\{.*\}/s', $aiResponseText, $match)) {
                    $parsed = json_decode($match[0], true);
                    if (!empty($parsed['action']) && in_array($parsed['action'], ['check_in', 'check_out', 'leaving_early', 'field_visit', 'back_in_office', 'leave_notice', 'unknown'])) {
                        return [
                            'action' => $parsed['action'],
                            'formatted_time' => !empty($parsed['formatted_time']) ? $parsed['formatted_time'] : $currentTimeStr,
                            'leave_type' => $parsed['leave_type'] ?? '',
                            'leave_reason' => $parsed['leave_reason'] ?? '',
                            'informed_person' => $parsed['informed_person'] ?? '',
                            'visit_location' => $parsed['visit_location'] ?? '',
                            'raw_text' => $text,
                            'ai_used' => true
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback to local rule engine if AI times out or errors
        }

        return null;
    }

    /**
     * Find Employee in Database by Phone Number, Employee Code, or Name
     */
    public function findEmployee($phone, $name = '', $rawText = '') {
        $cleanPhone = $this->cleanPhoneNumber($phone);

        // 1. Match by Employee Code in Message (e.g. "DP-104", "DP104", "EMP-104")
        if (!empty($rawText) && preg_match('/\b(DP-?\d{1,4}|EMP-?\d{1,4})\b/i', $rawText, $codeMatch)) {
            $codeClean = strtoupper(str_replace('-', '', $codeMatch[1]));
            $codeHyphen = preg_replace('/^([A-Z]+)(\d+)$/', '$1-$2', $codeClean);
            
            $stmtCode = $this->pdo->prepare("
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code, p.phone as profile_phone
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1 AND (
                    REPLACE(p.emp_code, '-', '') = ? 
                    OR p.emp_code = ? 
                    OR REPLACE(e.emp_code, '-', '') = ?
                    OR e.emp_code = ?
                )
                LIMIT 1
            ");
            $stmtCode->execute([$codeClean, $codeHyphen, $codeClean, $codeHyphen]);
            $emp = $stmtCode->fetch(PDO::FETCH_ASSOC);
            if ($emp) return $emp;
        }

        // 2. Match by Phone / WhatsApp Number
        if (!empty($cleanPhone)) {
            $variants = [$cleanPhone];
            if (substr($cleanPhone, 0, 1) === '0') {
                $variants[] = '92' . substr($cleanPhone, 1);
                $variants[] = substr($cleanPhone, 1);
            }

            $placeholders = implode(',', array_fill(0, count($variants), '?'));
            $sql = "
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code, p.phone as profile_phone
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1 AND (
                    REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '+', ''), '92', '0') IN ({$placeholders})
                    OR REPLACE(REPLACE(REPLACE(REPLACE(p.whatsapp_number, ' ', ''), '-', ''), '+', ''), '92', '0') IN ({$placeholders})
                    OR REPLACE(REPLACE(REPLACE(REPLACE(p.emergency_phone, ' ', ''), '-', ''), '+', ''), '92', '0') IN ({$placeholders})
                    OR REPLACE(REPLACE(REPLACE(REPLACE(e.whatsapp_number, ' ', ''), '-', ''), '+', ''), '92', '0') IN ({$placeholders})
                    OR REPLACE(REPLACE(REPLACE(REPLACE(e.phone, ' ', ''), '-', ''), '+', ''), '92', '0') IN ({$placeholders})
                )
                LIMIT 1
            ";
            $allParams = array_merge($variants, $variants, $variants, $variants, $variants);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($allParams);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp) return $emp;
        }

        // 3. Fallback: Match by Push Name / Contact Name
        if (!empty($name)) {
            $cleanName = strtolower(trim($name));
            // Exact name match
            $stmt = $this->pdo->prepare("
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1 AND LOWER(TRIM(e.name)) = ?
                LIMIT 1
            ");
            $stmt->execute([$cleanName]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp) return $emp;

            // Partial first/last name search
            $stmt = $this->pdo->prepare("
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1 AND (
                    ? LIKE CONCAT('%', LOWER(TRIM(e.name)), '%')
                    OR LOWER(TRIM(e.name)) LIKE CONCAT('%', ?, '%')
                )
                ORDER BY LENGTH(e.name) DESC
                LIMIT 1
            ");
            $stmt->execute([$cleanName, $cleanName]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp) return $emp;
        }

        // 4. Fallback: Scan message text for Phone numbers or Employee Names
        if (!empty($rawText)) {
            // Check for phone number inside message text (e.g. "03044301182 in" or "in 03044301182")
            if (preg_match('/\b(03\d{9}|923\d{9})\b/', $rawText, $phoneMatch)) {
                $foundPhone = $this->cleanPhoneNumber($phoneMatch[1]);
                $emp = $this->findEmployee($foundPhone, '', '');
                if ($emp) return $emp;
            }

            // Check if any active employee name is mentioned in the message text
            $stmtAll = $this->pdo->query("
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1
                ORDER BY LENGTH(e.name) DESC
            ");
            $allEmployees = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
            $lowerText = ' ' . strtolower($rawText) . ' ';
            foreach ($allEmployees as $empCandidate) {
                $candidateName = strtolower(trim($empCandidate['name']));
                if (strlen($candidateName) >= 4 && strpos($lowerText, $candidateName) !== false) {
                    return $empCandidate;
                }
            }
        }

        return null;
    }

    /**
     * Apply Parsed Action directly into daily_sheets and hr_leaves
     */
    private function applyToDailySheet($employee, $action, $timeStr, $date, $rawText, $parsedData = []) {
        $empId = (int)$employee['id'];

        // Find existing sheet for this date
        $stmt = $this->pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Check-In Action
        if ($action === 'check_in') {
            if ($sheet) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET check_in_time = COALESCE(check_in_time, ?),
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([$timeStr, "[WhatsApp] In at {$timeStr}", $sheet['id']]);
                return [
                    'sheet_id' => $sheet['id'],
                    'status' => 'applied',
                    'remarks' => "Checked In at {$timeStr} for {$employee['name']}."
                ];
            } else {
                $stmtIn = $this->pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, remarks, is_locked)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmtIn->execute([$empId, $date, $timeStr, "[WhatsApp] In at {$timeStr}"]);
                $newId = $this->pdo->lastInsertId();
                return [
                    'sheet_id' => $newId,
                    'status' => 'applied',
                    'remarks' => "Created Check-In at {$timeStr} for {$employee['name']}."
                ];
            }
        }

        // 2. Field Visit Action (Going to Location/Club/Organic Village - Keeps shift ACTIVE)
        if ($action === 'field_visit') {
            $locationNote = !empty($parsedData['visit_location']) ? $parsedData['visit_location'] : $rawText;
            $note = "[WhatsApp] 🚗 Field Visit at {$timeStr}: {$locationNote}";

            if ($sheet) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET check_in_time = COALESCE(check_in_time, ?),
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        work_summary = CONCAT(COALESCE(work_summary, ''), IF(work_summary IS NULL OR work_summary = '', '', ' | '), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([$timeStr, $note, "Field Visit: {$locationNote}", $sheet['id']]);
                return [
                    'sheet_id' => $sheet['id'],
                    'status' => 'applied',
                    'remarks' => "Logged Field Visit ({$locationNote}) — Shift remains ACTIVE on duty."
                ];
            } else {
                $stmtIn = $this->pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, remarks, work_summary, is_locked)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmtIn->execute([$empId, $date, $timeStr, $note, "Field Visit: {$locationNote}"]);
                $newId = $this->pdo->lastInsertId();
                return [
                    'sheet_id' => $newId,
                    'status' => 'applied',
                    'remarks' => "Started Shift & Logged Field Visit ({$locationNote})."
                ];
            }
        }

        // 3. Back in Office Action (Returned from visit - Keeps shift ACTIVE)
        if ($action === 'back_in_office') {
            $note = "[WhatsApp] 🏢 Back in Office at {$timeStr}";
            if ($sheet) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([$note, $sheet['id']]);
                return [
                    'sheet_id' => $sheet['id'],
                    'status' => 'applied',
                    'remarks' => "Logged Return to Office at {$timeStr} — Shift active on duty."
                ];
            } else {
                $stmtIn = $this->pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, remarks, is_locked)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmtIn->execute([$empId, $date, $timeStr, $note]);
                $newId = $this->pdo->lastInsertId();
                return [
                    'sheet_id' => $newId,
                    'status' => 'applied',
                    'remarks' => "Recorded In Office at {$timeStr}."
                ];
            }
        }

        // 4. Leave Notice Action (Auto-Marks Leave with Reason & Informed Authority)
        if ($action === 'leave_notice') {
            $leaveType = $parsedData['leave_type'] ?? 'emergency';
            $leaveReason = !empty($parsedData['leave_reason']) ? $parsedData['leave_reason'] : $rawText;
            $informed = !empty($parsedData['informed_person']) ? " (Informed: {$parsedData['informed_person']})" : '';
            $note = "[WhatsApp Leave] {$leaveReason}{$informed}";

            // Insert or Update hr_leaves table
            try {
                $stmtLeave = $this->pdo->prepare("
                    INSERT INTO hr_leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status, created_at)
                    VALUES (?, ?, ?, ?, 1, ?, 'approved', CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'approved'
                ");
                $stmtLeave->execute([$empId, $leaveType, $date, $date, $note]);
            } catch (Exception $e) {}

            // Update daily_sheets remarks
            if ($sheet) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([$note, $sheet['id']]);
                $sheetId = $sheet['id'];
            } else {
                $stmtIn = $this->pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, remarks, is_locked)
                    VALUES (?, ?, ?, 0)
                ");
                $stmtIn->execute([$empId, $date, $note]);
                $sheetId = $this->pdo->lastInsertId();
            }

            return [
                'sheet_id' => $sheetId,
                'status' => 'applied',
                'remarks' => "Auto-Marked Leave ({$leaveType}) with reason: '{$leaveReason}'."
            ];
        }

        // 5. Check-Out & Leaving Early Actions
        if ($action === 'check_out' || $action === 'leaving_early') {
            $checkInTime = $sheet['check_in_time'] ?? '09:00 AM';
            $inTs = strtotime($date . ' ' . $checkInTime);
            $outTs = strtotime($date . ' ' . $timeStr);

            if ($outTs < $inTs) {
                $outTs += 86400;
            }

            $dutySeconds = max(0, $outTs - $inTs);
            $dutyFormatted = sprintf('%dh %02dm', floor($dutySeconds / 3600), floor(($dutySeconds % 3600) / 60));

            $note = ($action === 'leaving_early') 
                ? "[WhatsApp] ⚠️ Early Leave at {$timeStr} ('{$rawText}')" 
                : "[WhatsApp] Out at {$timeStr}";

            if ($sheet) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET check_out_time = ?,
                        total_duty_hours = ?,
                        total_duty_seconds = ?,
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([$timeStr, $dutyFormatted, $dutySeconds, $note, $sheet['id']]);
                return [
                    'sheet_id' => $sheet['id'],
                    'status' => 'applied',
                    'remarks' => "Recorded Check-Out at {$timeStr} (Total Duty: {$dutyFormatted}) for {$employee['name']}."
                ];
            } else {
                $stmtIn = $this->pdo->prepare("
                    INSERT INTO daily_sheets 
                        (employee_id, sheet_date, check_in_time, check_out_time, total_duty_hours, total_duty_seconds, remarks, is_locked)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmtIn->execute([$empId, $date, $checkInTime, $timeStr, $dutyFormatted, $dutySeconds, $note]);
                $newId = $this->pdo->lastInsertId();
                return [
                    'sheet_id' => $newId,
                    'status' => 'applied',
                    'remarks' => "Created Check-Out record at {$timeStr} (Duty: {$dutyFormatted}) for {$employee['name']}."
                ];
            }
        }

        return [
            'sheet_id' => null,
            'status' => 'ignored',
            'remarks' => "Unknown action '{$action}'."
        ];
    }

    /**
     * Send WhatsApp Message via OrbitSend API
     */
    public function sendWhatsAppMessage($settings, $recipient, $messageText) {
        $secret = $settings['api_secret'] ?? '';
        $account = $settings['unique_id'] ?? ($settings['account'] ?? '');
        $apiUrl = $settings['api_url'] ?? 'https://app.orbitsend.com/api/send/whatsapp';

        if (!$secret || !$account || !$recipient) {
            return false;
        }

        $postFields = [
            'secret' => $secret,
            'account' => $account,
            'recipient' => $recipient,
            'type' => 'text',
            'message' => $messageText,
            'priority' => 1
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Fetch WhatsApp Groups from OrbitSend API
     */
    public function fetchWhatsAppGroups() {
        $settings = $this->getSettings();
        $secret = $settings['api_secret'] ?? '';
        $unique = $settings['unique_id'] ?? ($settings['account'] ?? '');

        if (!$secret || !$unique) {
            return ['success' => false, 'message' => 'API Secret and WhatsApp Unique ID are required.'];
        }

        $url = "https://app.orbitsend.com/api/get/wa.groups?secret=" . urlencode($secret) . "&unique=" . urlencode($unique);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return ['success' => false, 'message' => 'Failed to connect to OrbitSend API.'];
        }

        $data = json_decode($response, true);
        return [
            'success' => isset($data['status']) && (int)$data['status'] === 200,
            'data' => $data['data'] ?? [],
            'raw' => $data
        ];
    }

    /**
     * Fetch WhatsApp Accounts from OrbitSend API
     */
    public function fetchWhatsAppAccounts() {
        $settings = $this->getSettings();
        $secret = $settings['api_secret'] ?? '';

        if (!$secret) {
            return ['success' => false, 'message' => 'OrbitSend API Secret is required.'];
        }

        $url = "https://app.orbitsend.com/api/get/wa.accounts?secret=" . urlencode($secret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return ['success' => false, 'message' => 'Failed to connect to OrbitSend API.'];
        }

        $data = json_decode($response, true);
        return [
            'success' => isset($data['status']) && (int)$data['status'] === 200,
            'data' => $data['data'] ?? [],
            'raw' => $data
        ];
    }

    private function generateReplyMessage($empName, $action, $timeStr) {
        if ($action === 'check_in') {
            return "✅ *Attendance Recorded*\n👤 {$empName}\n🕒 Checked In: {$timeStr}";
        } elseif ($action === 'leaving_early') {
            return "⚠️ *Early Departure Recorded*\n👤 {$empName}\n🕒 Leaving At: {$timeStr}";
        } else {
            return "🚪 *Duty Completed*\n👤 {$empName}\n🕒 Checked Out: {$timeStr}";
        }
    }
}
