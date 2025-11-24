<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Illuminate\Support\Facades\Log;

class ReviewerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $recipientEmail;
    public $emailSubject;
    public $pdfContent;
    public $pdfFileName;
    public $examData;

    public function __construct($to, $subject, $pdfContent = null, $pdfFileName = null, $examData = [])
    {
        $this->recipientEmail = $to;
        $this->emailSubject = $subject;
        $this->pdfContent = $pdfContent;
        $this->pdfFileName = $pdfFileName ?? 'exam-record.pdf';
        $this->examData = $examData;
    }

    public function build()
    {
        try {
            // Setup Twig
            $loader = new FilesystemLoader(resource_path('views/emails'));

            $mail = $this->subject($this->emailSubject)->html($this->getSimpleFallback());
            
            // Attach PDF from base64 content
            if ($this->pdfContent) {
                $mail->attachData(
                    base64_decode($this->pdfContent),
                    $this->pdfFileName,
                    ['mime' => 'application/pdf']
                );
                Log::info("PDF attached to email: {$this->pdfFileName}");
            } else {
                Log::warning("No PDF content to attach");
            }
            
            return $mail;
            
        } catch (\Exception $e) {
            Log::error('Twig rendering failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to simple HTML
            $mail = $this->subject($this->emailSubject)
                        ->html($this->getSimpleFallback());
            
            // Still attach PDF even on fallback
            if ($this->pdfContent) {
                $mail->attachData(
                    base64_decode($this->pdfContent),
                    $this->pdfFileName,
                    ['mime' => 'application/pdf']
                );
            }
            
            return $mail;
        }
    }

    protected function getSimpleFallback()
    {
        $student_firstname = $this->examData['student']['first_name'] ?? 'Student';
        $student_lastname = $this->examData['student']['last_name'] ?? 'Student';
        $studentName = $student_firstname . ' ' . $student_lastname;
        $studentName = "$student_firstname $student_lastname";

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
                .header { background: #1e40af; color: white; padding: 20px; border-radius: 8px 8px 0 0; margin: -30px -30px 20px; }
                .button { background: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>📋 Exam Record</h2>
                </div>
                <p>Hello <strong>{$studentName}</strong>!</p>
                <p>Please find attached reviewer PDF.</p>
                <p>Best regards,<br><strong>PE-PROJECT Team</strong></p>
            </div>
        </body>
        </html>
        HTML;
    }
}