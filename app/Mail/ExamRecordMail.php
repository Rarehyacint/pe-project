<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExamRecordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $examRecordData;
    public $studentData;
    public $pdfPath;

    public function __construct($examRecordData, $studentData, $pdfPath)
    {
        $this->examRecordData = $examRecordData;
        $this->studentData = $studentData;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        // Add safety check to prevent division by zero
        $maxScore = $this->examRecordData['exam']['max_score'] ?? 1;
        $percentage = $maxScore > 0 
            ? ($this->examRecordData['total_score'] / $maxScore) * 100 
            : 0;
        
        // Prepare student name - handle both 'name' and 'first_name'/'last_name' formats
        $studentName = $this->getStudentName();
        
        // Prepare exam date - handle both 'exam.date' and 'date_taken' formats
        $examDate = $this->getExamDate();
        
        // Use simple HTML directly (no Twig dependency)
        $html = $this->buildSimpleHtml(number_format($percentage, 1), $studentName, $examDate);
        
        return $this->subject($this->examRecordData['exam']['name'] . ' - Exam Record')
                    ->html($html)
                    ->attach($this->pdfPath, [
                        'as' => 'exam_record.pdf',
                        'mime' => 'application/pdf',
                    ]);
    }
    
    private function getStudentName()
    {
        // Check if 'name' exists
        if (isset($this->studentData['name']) && !empty($this->studentData['name'])) {
            return $this->studentData['name'];
        }
        
        // Check if 'first_name' and 'last_name' exist
        if (isset($this->studentData['first_name']) || isset($this->studentData['last_name'])) {
            $firstName = $this->studentData['first_name'] ?? '';
            $lastName = $this->studentData['last_name'] ?? '';
            return trim($firstName . ' ' . $lastName);
        }
        
        // Check if 'username' exists
        if (isset($this->studentData['username']) && !empty($this->studentData['username'])) {
            return $this->studentData['username'];
        }
        
        return 'Student';
    }
    
    private function getExamDate()
    {
        // Check if exam.date exists
        if (isset($this->examRecordData['exam']['date'])) {
            return $this->examRecordData['exam']['date'];
        }
        
        // Check if date_taken exists
        if (isset($this->examRecordData['date_taken'])) {
            return $this->examRecordData['date_taken'];
        }
        
        return date('Y-m-d');
    }
    
    private function buildSimpleHtml($percentage, $studentName, $examDate)
    {
        $examName = htmlspecialchars($this->examRecordData['exam']['name'] ?? 'Exam');
        $studentName = htmlspecialchars($studentName);
        $studentId = htmlspecialchars($this->studentData['id'] ?? 'N/A');
        $examDate = date('F d, Y', strtotime($examDate));
        $totalScore = $this->examRecordData['total_score'];
        $maxScore = $this->examRecordData['exam']['max_score'];
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .student-info {
            margin: 20px 0;
        }
        .info-row {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$examName}</h1>
        <p>Exam Record</p>
    </div>
    
    <div class="content">
        <p>Dear {$studentName},</p>
        
        <p>Please find your exam record for <strong>{$examName}</strong>.</p>
        
        <div class="student-info">
            <div class="info-row">
                <span class="label">Score:</span> {$totalScore} / {$maxScore}
            </div>
            <div class="info-row">
                <span class="label">Percentage:</span> {$percentage}%
            </div>
        </div>
        
        <p>The complete details of your exam record are attached to this email as a PDF file.</p>
        
        <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
    </div>
    
    <div class="footer">
        <p>Thank you,<br>
        School Administration</p>
        <p><em>This is an automated message. Please do not reply to this email.</em></p>
    </div>
</body>
</html>
HTML;
    }
}