<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #0E1129 0%, #3B82F6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .content {
            padding: 40px 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #3B82F6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .info-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #0E1129;
            font-weight: 500;
        }
        .message-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }
        .message-box h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
            color: #0E1129;
        }
        .message-content {
            color: #4b5563;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .reply-button {
            display: inline-block;
            background: #3B82F6;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 New Contact Form Submission</h1>
        </div>

        <div class="content">
            <p style="font-size: 16px; color: #4b5563;">You have received a new message from the ModernPOS contact form.</p>

            <div class="info-box">
                <div class="info-label">From</div>
                <div class="info-value">{{ $senderEmail }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Subject</div>
                <div class="info-value">{{ $subject }}</div>
            </div>

            <div class="message-box">
                <h3>Message:</h3>
                <div class="message-content">{{ $messageContent }}</div>
            </div>

            <div style="text-align: center;">
                <a href="mailto:{{ $senderEmail }}" class="reply-button">Reply to {{ $senderEmail }}</a>
            </div>
        </div>

        <div class="footer">
            <p><strong>ModernPOS</strong> by Doitix Tech Labs</p>
            <p>Nairobi, Kenya | +254 759 814 390</p>
            <p style="font-size: 12px; margin-top: 15px;">This is an automated message from your contact form.</p>
        </div>
    </div>
</body>
</html>
