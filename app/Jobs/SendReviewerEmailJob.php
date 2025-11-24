<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReviewerMail;

class SendReviewerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = 30;

    public $emailList;
    public $subject;
    public $pdfContent;
    public $pdfFileName;
    public $examData;

    public function __construct($emailList, $subject, $pdfPath = null, $examData = [])
    {
        $this->emailList = is_array($emailList) ? $emailList : [$emailList];
        $this->subject = $subject;
        $this->examData = $examData;
        
        // Store PDF content as base64 instead of path
        if ($pdfPath && file_exists($pdfPath)) {
            $this->pdfContent = base64_encode(file_get_contents($pdfPath));
            
            // Use original filename instead of hardcoded name
            $this->pdfFileName = basename($pdfPath);
            
            Log::info("PDF loaded successfully: {$this->pdfFileName} - " . strlen($this->pdfContent) . " bytes");
        } else {
            Log::warning("PDF file not found: {$pdfPath}");
            $this->pdfContent = null;
            $this->pdfFileName = null;
        }
    }

    public function handle()
    {
        foreach ($this->emailList as $email) {
            try {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::warning("Invalid email format: {$email}");
                    continue;
                }

                Mail::to($email)->send(new ReviewerMail(
                    $email,
                    $this->subject,
                    $this->pdfContent,
                    $this->pdfFileName,
                    $this->examData
                ));
                
                Log::info("Reviewer email sent successfully to: {$email} with file: {$this->pdfFileName}");
                
            } catch (\Exception $e) {
                Log::error("Failed to send reviewer email to {$email}: " . $e->getMessage(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendReviewerEmailJob failed permanently', [
            'error' => $exception->getMessage(),
            'emails' => $this->emailList,
            'filename' => $this->pdfFileName,
            'trace' => $exception->getTraceAsString()
        ]);
    }
}