<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ExamRecordMail;
use Dompdf\Dompdf;
use Dompdf\Options;

class SendExamRecordEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = 30;

    protected $emailList;
    protected $topic;
    protected $examRecordData;
    protected $studentData;

    public function __construct($emailList, $topic, $examRecordData, $studentData)
    {
        $this->emailList = $emailList;
        $this->topic = $topic;
        $this->examRecordData = $examRecordData;
        $this->studentData = $studentData;
    }

    public function handle()
    {
        try {
            // Create PDF directory if not exists
            $tempDir = storage_path('app/temp_attachments');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // Generate and save PDF
            $pdfPath = $tempDir . '/' . uniqid() . '_exam_record.pdf';
            $this->createExamRecordPDF($pdfPath);
            
            // Send email to each recipient
            foreach ($this->emailList as $email) {
                try {
                    // Send email using Laravel's Mail facade (removed $email parameter from ExamRecordMail)
                    Mail::to($email)
                        ->send(new ExamRecordMail(
                            $this->examRecordData,
                            $this->studentData,
                            $pdfPath
                        ));
                    
                    Log::info("Exam record email sent successfully to: {$email}");
                } catch (\Exception $e) {
                    Log::error("Failed to send exam record email to {$email}: " . $e->getMessage());
                    continue;
                }
            }

            // Clean up the temporary PDF file
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

        } catch (\Exception $e) {
            Log::error("Failed to process exam record email job: " . $e->getMessage());
            throw $e;
        }
    }

    protected function createExamRecordPDF($filePath)
    {
        $percentage = ($this->examRecordData['total_score'] / $this->examRecordData['exam']['max_score']) * 100;
        $performanceData = $this->getPerformanceAnalysis($percentage);
        $html = $this->generateExamRecordHTML($percentage, $performanceData);
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($filePath, $dompdf->output());
    }

    protected function getPerformanceAnalysis($percentage)
    {
        if ($percentage >= 90) return ['level' => 'Excellent', 'recommendation' => 'Keep up the outstanding work!'];
        if ($percentage >= 80) return ['level' => 'Very Good', 'recommendation' => 'Continue your good study habits.'];
        if ($percentage >= 70) return ['level' => 'Good', 'recommendation' => 'Focus on areas that need improvement.'];
        if ($percentage >= 60) return ['level' => 'Satisfactory', 'recommendation' => 'Review study materials and seek help if needed.'];
        return ['level' => 'Needs Improvement', 'recommendation' => 'Consider additional study time and tutoring.'];
    }

    protected function generateExamRecordHTML($percentage, $performanceData)
    {
        $subjectsHTML = '';
        if (isset($this->examRecordData['subjects']) && count($this->examRecordData['subjects']) > 0) {
            $subjectsHTML = '<div class="section"><div class="section-title">SUBJECT BREAKDOWN</div><table><thead><tr><th>Subject</th><th>Score</th><th>Max</th><th>%</th></tr></thead><tbody>';
            foreach ($this->examRecordData['subjects'] as $s) {
                $sp = $s['score'] > 0 ? ($s['score_obtained'] / $s['score']) * 100 : 0;
                $subjectsHTML .= '<tr><td>' . htmlspecialchars($s['subject_name']) . '</td><td>' . $s['score_obtained'] . '</td><td>' . $s['score'] . '</td><td>' . number_format($sp, 1) . '%</td></tr>';
            }
            $subjectsHTML .= '</tbody></table></div>';
        }
        
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;margin:40px;color:#333}.header{text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:20px;margin-bottom:30px}.header h1{color:#1e3a8a;margin:0;font-size:28px}.info-box{background:#f3f4f6;padding:15px;border-radius:8px;margin-bottom:20px}.info-item{margin:8px 0}.info-label{font-weight:bold;color:#1e3a8a}.section{margin:25px 0}.section-title{color:#1e3a8a;font-size:18px;font-weight:bold;border-bottom:2px solid #1e3a8a;padding-bottom:5px;margin-bottom:15px}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #d1d5db;padding:10px;text-align:left}th{background:#1e3a8a;color:white}tr:nth-child(even){background:#f9fafb}.score-highlight{background:#dbeafe;font-weight:bold;font-size:16px}.footer{margin-top:40px;padding-top:20px;border-top:2px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280}</style></head><body><div class="header"><h1>EXAM RECORD</h1></div><div class="info-box"><div class="info-item"><span class="info-label">Student:</span> ' . htmlspecialchars($this->studentData['first_name']) . ' ' . htmlspecialchars($this->studentData['last_name']) . '</div><div class="info-item"><span class="info-label">Exam:</span> ' . htmlspecialchars($this->examRecordData['exam']['name']) . '</div><div class="info-item"><span class="info-label">Attempt:</span> #' . $this->examRecordData['attempt'] . '</div><div class="info-item score-highlight"><span class="info-label">Score:</span> ' . $this->examRecordData['total_score'] . '/' . $this->examRecordData['exam']['max_score'] . ' (' . number_format($percentage, 1) . '%)</div><div class="info-item"><span class="info-label">Status:</span> ' . ucfirst($this->examRecordData['status']) . '</div><div class="info-item"><span class="info-label">Date:</span> ' . date('m/d/Y', strtotime($this->examRecordData['date_taken'])) . '</div><div class="info-item"><span class="info-label">Time:</span> ' . $this->examRecordData['time_taken'] . ' min</div></div>' . $subjectsHTML . '<div class="section"><div class="section-title">PERFORMANCE</div><div class="info-box"><div class="info-item"><span class="info-label">Percentage:</span> ' . number_format($percentage, 1) . '%</div><div class="info-item"><span class="info-label">Level:</span> ' . $performanceData['level'] . '</div><div class="info-item"><span class="info-label">Recommendation:</span> ' . $performanceData['recommendation'] . '</div></div></div><div class="footer"><p>Generated: ' . date('F j, Y \a\t g:i A') . '</p></div></body></html>';
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendExamRecordEmailJob failed permanently: ' . $exception->getMessage());
    }
}