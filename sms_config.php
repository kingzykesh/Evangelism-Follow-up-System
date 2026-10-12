<?php
require_once 'functions.php';

$test_phone = '2348100163853'; // recipient’s number (international format, no +)
$test_message = 'Hello Kingdom! This is a test message from Evangelism System.';

if (send_sms($test_phone, $test_message)) {
    echo "✅ Message sent successfully!";
} else {
    echo "❌ Failed to send message.";
}
