<?php
/**
 * HodWhatsAppService: Dedicated WhatsApp & AI Integration for HODs & Task Assigner
 * Powers per-HOD OrbitSend WhatsApp credentials, AI image vision parsing,
 * and automated employee task notifications.
 */

class HodWhatsAppService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get WhatsApp & AI settings for a specific HOD
     */
    public function getSettings($hodId) {
        $stmt = $this->pdo->prepare("SELECT * FROM hod_whatsapp_settings WHERE hod_id = ? LIMIT 1");
        $stmt->execute([(int)$hodId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch HOD's department
        $deptStmt = $this->pdo->prepare("SELECT department_id, name, phone, whatsapp_number FROM employees WHERE id = ?");
        $deptStmt->execute([(int)$hodId]);
        $emp = $deptStmt->fetch(PDO::FETCH_ASSOC);
        $deptId = $emp['department_id'] ?? null;

        // If no HOD-specific settings, check if there are department-level settings
        if (!$row && $deptId) {
            $stmtDept = $this->pdo->prepare("SELECT * FROM hod_whatsapp_settings WHERE department_id = ? LIMIT 1");
            $stmtDept->execute([(int)$deptId]);
            $row = $stmtDept->fetch(PDO::FETCH_ASSOC);
        }

        // Global fallback if no AI key configured on HOD level
        $globalStmt = $this->pdo->query("SELECT api_url, api_secret, unique_id, ai_provider, ai_api_key FROM whatsapp_attendance_settings WHERE id = 1");
        $global = $globalStmt ? $globalStmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$row) {
            return [
                'id' => null,
                'hod_id' => (int)$hodId,
                'department_id' => $deptId,
                'api_url' => 'https://app.orbitsend.com/api/send/whatsapp',
                'api_secret' => '',
                'masked_secret' => '',
                'unique_id' => '',
                'webhook_token' => '',
                'ai_provider' => $global['ai_provider'] ?? 'auto',
                'ai_api_key' => '',
                'has_ai_key' => !empty($global['ai_api_key']) || !empty(getenv('AI_API_KEY')) || !empty(getenv('GEMINI_API_KEY')),
                'is_enabled' => 1,
                'auto_send_on_assign' => 1,
                'is_configured' => false
            ];
        }

        $secret = $row['api_secret'] ?? '';
        $maskedSecret = '';
        if (!empty($secret)) {
            $len = strlen($secret);
            $maskedSecret = $len > 8 ? substr($secret, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($secret, -4) : '••••••••';
        }

        $aiKey = $row['ai_api_key'] ?? '';
        $hasAiKey = !empty($aiKey) || !empty($global['ai_api_key']) || !empty(getenv('AI_API_KEY')) || !empty(getenv('GEMINI_API_KEY'));

        return [
            'id' => (int)$row['id'],
            'hod_id' => (int)$row['hod_id'],
            'department_id' => $row['department_id'] ?: $deptId,
            'api_url' => $row['api_url'] ?: 'https://app.orbitsend.com/api/send/whatsapp',
            'api_secret' => $secret,
            'masked_secret' => $maskedSecret,
            'unique_id' => $row['unique_id'] ?? '',
            'webhook_token' => $row['webhook_token'] ?? '',
            'ai_provider' => $row['ai_provider'] ?: 'auto',
            'ai_api_key' => $aiKey,
            'has_ai_key' => $hasAiKey,
            'is_enabled' => (int)($row['is_enabled'] ?? 1),
            'auto_send_on_assign' => (int)($row['auto_send_on_assign'] ?? 1),
            'is_configured' => !empty($secret) && !empty($row['unique_id'])
        ];
    }

    /**
     * Save HOD WhatsApp & AI Settings
     */
    public function saveSettings($hodId, $data) {
        $hodId = (int)$hodId;
        $existing = $this->getSettings($hodId);

        $apiUrl = trim($data['api_url'] ?? 'https://app.orbitsend.com/api/send/whatsapp');
        if (empty($apiUrl)) $apiUrl = 'https://app.orbitsend.com/api/send/whatsapp';

        $apiSecret = trim($data['api_secret'] ?? '');
        // Keep existing secret if sent placeholder or empty
        if (empty($apiSecret) || strpos($apiSecret, '•') !== false) {
            $apiSecret = $existing['api_secret'] ?? '';
        }

        $uniqueId = trim($data['unique_id'] ?? '');
        $webhookToken = trim($data['webhook_token'] ?? '');
        $aiProvider = trim($data['ai_provider'] ?? 'auto');
        $aiApiKey = trim($data['ai_api_key'] ?? '');
        if (empty($aiApiKey) || strpos($aiApiKey, '•') !== false) {
            $aiApiKey = $existing['ai_api_key'] ?? '';
        }

        $isEnabled = isset($data['is_enabled']) ? (int)$data['is_enabled'] : 1;
        $autoSend = isset($data['auto_send_on_assign']) ? (int)$data['auto_send_on_assign'] : 1;

        // Fetch department id of HOD
        $deptStmt = $this->pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $deptStmt->execute([$hodId]);
        $deptId = $deptStmt->fetchColumn() ?: null;

        $stmt = $this->pdo->prepare("
            INSERT INTO hod_whatsapp_settings 
                (hod_id, department_id, api_url, api_secret, unique_id, webhook_token, ai_provider, ai_api_key, is_enabled, auto_send_on_assign)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                department_id = VALUES(department_id),
                api_url = VALUES(api_url),
                api_secret = VALUES(api_secret),
                unique_id = VALUES(unique_id),
                webhook_token = VALUES(webhook_token),
                ai_provider = VALUES(ai_provider),
                ai_api_key = VALUES(ai_api_key),
                is_enabled = VALUES(is_enabled),
                auto_send_on_assign = VALUES(auto_send_on_assign),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $hodId, $deptId, $apiUrl, $apiSecret, $uniqueId, $webhookToken, $aiProvider, $aiApiKey, $isEnabled, $autoSend
        ]);

        return [
            'success' => true,
            'message' => 'HOD WhatsApp & AI credentials saved successfully!'
        ];
    }

    /**
     * Clean phone number into international Pakistani format (e.g. 923001234567)
     */
    public function cleanPhoneNumber($phone) {
        if (!$phone) return '';
        $digits = preg_replace('/[^\d]/', '', (string)$phone);
        if (empty($digits)) return '';

        if (strpos($digits, '0092') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '92') === 0) {
            $digits = substr($digits, 2);
        } elseif (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '92' . $digits;
        } elseif (strlen($digits) === 12 && strpos($digits, '92') === 0) {
            return $digits;
        }

        return !empty($digits) ? $digits : '';
    }

    /**
     * Send Task Notification to Employee via HOD's OrbitSend WhatsApp Gateway
     */
    public function sendTaskWhatsAppNotification($taskId, $senderUserId = null) {
        $taskId = (int)$taskId;
        
        // Fetch task details
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   e.id AS emp_id, e.name AS emp_name, e.emp_code, e.phone AS emp_phone, e.whatsapp_number AS emp_whatsapp,
                   h.name AS hod_name, h.emp_code AS hod_code,
                   d.name AS dept_name
            FROM tasks t
            LEFT JOIN employees e ON t.assigned_to = e.id
            LEFT JOIN employees h ON t.assigned_by = h.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return ['success' => false, 'message' => 'Task not found.'];
        }

        $assignedBy = $senderUserId ?: ($task['assigned_by'] ?: 1);
        $settings = $this->getSettings($assignedBy);

        // Fall back to global settings if HOD hasn't set secret
        if (empty($settings['api_secret'])) {
            $globalStmt = $this->pdo->query("SELECT api_url, api_secret, unique_id FROM whatsapp_attendance_settings WHERE id = 1");
            $global = $globalStmt ? $globalStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($global && !empty($global['api_secret'])) {
                $settings['api_url'] = $global['api_url'] ?: $settings['api_url'];
                $settings['api_secret'] = $global['api_secret'];
                $settings['unique_id'] = $global['unique_id'] ?: $settings['unique_id'];
            }
        }

        if (empty($settings['api_secret']) || empty($settings['unique_id'])) {
            $this->updateTaskWhatsAppStatus($taskId, 0, 'OrbitSend credentials not configured for HOD');
            return [
                'success' => false,
                'message' => 'OrbitSend credentials (API Secret / Unique ID) not configured.'
            ];
        }

        // Determine recipient phone
        $rawPhone = !empty($task['emp_whatsapp']) ? $task['emp_whatsapp'] : $task['emp_phone'];
        $cleanPhone = $this->cleanPhoneNumber($rawPhone);

        if (empty($cleanPhone)) {
            $this->updateTaskWhatsAppStatus($taskId, 0, 'No WhatsApp/phone number on employee profile');
            return [
                'success' => false,
                'message' => "Employee {$task['emp_name']} has no phone/WhatsApp number in profile."
            ];
        }

        // Format professional notification message
        $empCodeDisplay = !empty($task['emp_code']) ? " [{$task['emp_code']}]" : "";
        $deptDisplay = !empty($task['dept_name']) ? $task['dept_name'] : ($task['department'] ?: 'Production');
        $hodDisplay = !empty($task['hod_name']) ? $task['hod_name'] : 'Head of Department';
        $assignedAt = date('d-M-Y h:i A', strtotime($task['created_at'] ?: 'now'));
        $dueDateDisplay = !empty($task['due_date']) ? date('d-M-Y', strtotime($task['due_date'])) : 'Today / Urgent';

        $msgLines = [];
        $msgLines[] = "🎯 *NEW TASK ASSIGNMENT*";
        $msgLines[] = "━━━━━━━━━━━━━━━━━━━━";
        $msgLines[] = "👤 *Assigned To:* " . $task['emp_name'] . $empCodeDisplay;
        $msgLines[] = "🏢 *Department:* " . $deptDisplay;
        $msgLines[] = "👑 *Assigned By:* " . $hodDisplay;
        $msgLines[] = "📌 *Task:* " . $task['title'];

        if (!empty($task['link'])) {
            $msgLines[] = "📁 *Path / Link:* " . $task['link'];
        }

        if (!empty($task['description'])) {
            $msgLines[] = "📝 *Instructions:*\n" . $task['description'];
        }

        $msgLines[] = "📅 *Due Date:* " . $dueDateDisplay;
        $msgLines[] = "⏰ *Assigned At:* " . $assignedAt;
        $msgLines[] = "━━━━━━━━━━━━━━━━━━━━";
        $msgLines[] = "_Discover Pakistan TV - Worksheet System_";

        $messageText = implode("\n", $msgLines);

        // Send via OrbitSend API
        $apiUrl = $settings['api_url'] ?: 'https://app.orbitsend.com/api/send/whatsapp';
        $postData = [
            'secret' => $settings['api_secret'],
            'unique' => $settings['unique_id'],
            'recipient' => $cleanPhone,
            'type' => 'text',
            'message' => $messageText
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $resJson = json_decode($response, true);
        $isSent = false;
        $statusMsg = '';

        if ($response && ($httpCode === 200 || $httpCode === 201)) {
            if (isset($resJson['status']) && ($resJson['status'] == 200 || $resJson['status'] == 'success' || $resJson['status'] == true)) {
                $isSent = true;
                $statusMsg = "Sent to {$cleanPhone} via OrbitSend";
            } elseif (isset($resJson['message']) && stripos($resJson['message'], 'success') !== false) {
                $isSent = true;
                $statusMsg = "Sent to {$cleanPhone} via OrbitSend";
            } else {
                $statusMsg = $resJson['message'] ?? 'OrbitSend returned non-success response';
            }
        } else {
            $statusMsg = $curlError ? "cURL error: {$curlError}" : "HTTP {$httpCode} from OrbitSend";
        }

        $this->updateTaskWhatsAppStatus($taskId, $isSent ? 1 : 0, $statusMsg);

        return [
            'success' => $isSent,
            'message' => $isSent ? "WhatsApp notification sent to {$task['emp_name']} ({$cleanPhone})!" : "WhatsApp send failed: {$statusMsg}",
            'recipient' => $cleanPhone,
            'response' => $resJson
        ];
    }

    private function updateTaskWhatsAppStatus($taskId, $sent, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE tasks SET whatsapp_sent = ?, whatsapp_status = ? WHERE id = ?");
            $stmt->execute([(int)$sent, substr($status, 0, 95), (int)$taskId]);
        } catch (Exception $e) {}
    }

    /**
     * AI Multimodal Vision Analysis: Reads Task Image / Screenshot
     * Extracts: file/folder path, employee code, task title, description, priority.
     */
    public function analyzeTaskImageWithAi($imageBase64, $hodId, $contextText = '') {
        $settings = $this->getSettings($hodId);

        // Resolve AI Key (HOD key -> Global key -> .env)
        $aiKey = $settings['ai_api_key'];
        if (empty($aiKey)) {
            $globalStmt = $this->pdo->query("SELECT ai_provider, ai_api_key FROM whatsapp_attendance_settings WHERE id = 1");
            $global = $globalStmt ? $globalStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($global && !empty($global['ai_api_key'])) {
                $aiKey = $global['ai_api_key'];
                if ($settings['ai_provider'] === 'auto') {
                    $settings['ai_provider'] = $global['ai_provider'] ?: 'auto';
                }
            }
        }
        if (empty($aiKey)) {
            $aiKey = getenv('AI_API_KEY') ?: (getenv('GEMINI_API_KEY') ?: getenv('OPENAI_API_KEY'));
        }

        if (empty($aiKey)) {
            return [
                'success' => false,
                'message' => 'No AI API Key configured. Please enter your Google Gemini or OpenAI API Key in WhatsApp & AI Settings.'
            ];
        }

        // Clean Base64 Data & Detect MIME Type
        $mimeType = 'image/jpeg';
        $pureBase64 = $imageBase64;
        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/s', $imageBase64, $matches)) {
            $mimeType = $matches[1];
            $pureBase64 = $matches[2];
        }

        $systemPrompt = "You are an intelligent AI workflow assistant for Discover Pakistan TV (a premier national satellite HD broadcast television channel and digital media company in Lahore, Pakistan).
The user is an HOD or supervisor who has uploaded a screenshot or photo to assign a task to an employee.
This image may show:
- A Windows File Explorer folder path (e.g. C:\\Projects\\..., D:\\Footage\\..., Z:\\Nas\\..., \\\\server\\share\\...)
- Premiere Pro / After Effects project panel, sequence name, media drive path, timecodes, or edit cues
- An error report, document, handwritten assignment note, script outline, or WhatsApp message
- An Employee Code (e.g., DP1114, DP-104, DP025) or employee name

Carefully examine all text, banners, address bars, file trees, file names, and visual contents in the image.
Extract the following fields into strictly valid JSON format:

1. \"file_or_folder_path\": Any directory path, file path, network drive path (e.g. C:\\..., D:\\..., \\\\server\\...), or Google Drive/web URL. If quotes surround the path, strip them. If no path is found, return \"\".
2. \"employee_code\": Any employee ID/code found (e.g. DP1114, DP-1114, DP-104, DP025). If no code found, return \"\".
3. \"task_title\": A concise, professional, action-oriented task title summarizing what needs to be done based on the image (e.g., \"Edit Episode 14 Promo Cut\", \"Fix Audio Noise on Segment 2\", \"Render Social Media Reels\").
4. \"description\": A clear, comprehensive description containing all relevant details, scene names, file names, notes, instructions, and bullet points observed in the image that the employee needs to know.
5. \"priority\": One of: \"urgent\", \"high\", \"medium\", or \"low\".

Context hints from user: \"{$contextText}\"

Output format:
{
  \"file_or_folder_path\": \"...\",
  \"employee_code\": \"...\",
  \"task_title\": \"...\",
  \"description\": \"...\",
  \"priority\": \"...\"
}";

        $provider = strtolower($settings['ai_provider'] ?? 'auto');
        $aiResponseText = '';

        try {
            if ($provider === 'openai' || ($provider === 'auto' && strpos($aiKey, 'sk-') === 0)) {
                // OpenAI GPT-4o-mini Vision
                $payload = [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $systemPrompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$pureBase64}",
                                        'detail' => 'high'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1
                ];

                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $aiKey
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch);
                curl_close($ch);

                if ($res) {
                    $json = json_decode($res, true);
                    $aiResponseText = $json['choices'][0]['message']['content'] ?? '';
                }
            } else {
                // Google Gemini Vision (gemini-1.5-flash)
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($aiKey);
                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $pureBase64
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json'
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch);
                curl_close($ch);

                if ($res) {
                    $json = json_decode($res, true);
                    $aiResponseText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                }
            }

            if (empty($aiResponseText)) {
                return [
                    'success' => false,
                    'message' => 'AI returned an empty response. Please verify your AI API Key.'
                ];
            }

            // Extract JSON from response
            $parsed = null;
            if (preg_match('/\{.*\}/s', $aiResponseText, $match)) {
                $parsed = json_decode($match[0], true);
            }

            if (!$parsed) {
                return [
                    'success' => false,
                    'message' => 'Failed to parse AI response into structured task data.',
                    'raw' => $aiResponseText
                ];
            }

            // Clean extracted path
            $filePath = trim($parsed['file_or_folder_path'] ?? '');
            $filePath = trim($filePath, " \t\n\r\0\x0B\"'");

            $empCode = strtoupper(trim($parsed['employee_code'] ?? ''));
            // Normalize DP code format (e.g. "DP-1114" -> "DP1114" or vice versa)
            $matchedEmployee = null;
            if (!empty($empCode)) {
                $codeDigits = preg_replace('/[^\d]/', '', $empCode);
                $codeHyphen = 'DP-' . $codeDigits;
                $codeDirect = 'DP' . $codeDigits;

                $empStmt = $this->pdo->prepare("
                    SELECT id, name, emp_code, phone, whatsapp_number, designation, department_id 
                    FROM employees 
                    WHERE emp_code = ? OR emp_code = ? OR emp_code = ? OR emp_code LIKE ?
                    LIMIT 1
                ");
                $empStmt->execute([$empCode, $codeDirect, $codeHyphen, "%{$codeDigits}"]);
                $matchedEmployee = $empStmt->fetch(PDO::FETCH_ASSOC);
            }

            return [
                'success' => true,
                'data' => [
                    'file_or_folder_path' => $filePath,
                    'employee_code' => $empCode,
                    'task_title' => trim($parsed['task_title'] ?? ''),
                    'description' => trim($parsed['description'] ?? ''),
                    'priority' => in_array(strtolower($parsed['priority'] ?? ''), ['low', 'medium', 'high', 'urgent']) ? strtolower($parsed['priority']) : 'medium',
                    'matched_employee' => $matchedEmployee
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error during AI analysis: ' . $e->getMessage()
            ];
        }
    }
}
