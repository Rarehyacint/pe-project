<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeDataSeeder extends Seeder
{
    protected $subjects_with_topics = [
        [
            'name' => 'Introduction to Computer Science',
            'topics' => [
                ['name' => 'Basic computer hardware and software'],
                ['name' => 'History of computing'],
                ['name' => 'Problem-solving and algorithmic thinking'],
                ['name' => 'Introduction to programming concepts'],
                ['name' => 'Overview of computer science branches'],
            ]
        ],
        [
            'name' => 'Introduction to Programming',
            'topics' => [
                ['name' => 'Basic syntax and semantics (e.g., variables, data types)'],
                ['name' => 'Control structures (loops, conditionals)'],
                ['name' => 'Functions and modular programming'],
                ['name' => 'Input/output handling'],
                ['name' => 'Basic error handling and debugging'],
            ]
        ],
        [
            'name' => 'Object-Oriented Programming',
            'topics' => [
                ['name' => 'Classes and objects'],
                ['name' => 'Encapsulation, inheritance, and polymorphism'],
                ['name' => 'Constructors and destructors'],
                ['name' => 'Object-oriented design principles'],
                ['name' => 'Exception handling in OOP'],
            ]
        ],
        [
            'name' => 'Data Structures and Algorithms',
            'topics' => [
                ['name' => 'Arrays, stacks, queues, linked lists'],
                ['name' => 'Trees, graphs, and hash tables'],
                ['name' => 'Sorting and searching algorithms'],
                ['name' => 'Time complexity analysis (Big-O notation)'],
                ['name' => 'Recursion and iteration'],
            ]
        ],
        [
            'name' => 'Computer Organization and Architecture',
            'topics' => [
                ['name' => 'Basic hardware components (CPU, RAM, I/O devices)'],
                ['name' => 'Machine-level architecture (binary, instructions)'],
                ['name' => 'Assembly language basics'],
                ['name' => 'Memory hierarchy (cache, RAM, hard drives)'],
                ['name' => 'Input/Output operations'],
            ]
        ],
        [
            'name' => 'Operating Systems',
            'topics' => [
                ['name' => 'Processes and threads'],
                ['name' => 'Memory management (paging, segmentation)'],
                ['name' => 'File systems and storage management'],
                ['name' => 'Scheduling algorithms (FIFO, Round Robin)'],
                ['name' => 'Inter-process communication (IPC)'],
            ]
        ],
        [
            'name' => 'Database Management Systems',
            'topics' => [
                ['name' => 'Database design (ER diagrams, normalization)'],
                ['name' => 'SQL basics (SELECT, INSERT, UPDATE, DELETE)'],
                ['name' => 'Relational model'],
                ['name' => 'Indexing and optimization techniques'],
                ['name' => 'Transactions and ACID properties'],
            ]
        ],
        [
            'name' => 'Computer Networks',
            'topics' => [
                ['name' => 'OSI model and TCP/IP stack'],
                ['name' => 'IP addressing and subnetting'],
                ['name' => 'Routing and switching'],
                ['name' => 'Network protocols (HTTP, FTP, DNS)'],
                ['name' => 'Introduction to wireless networks'],
            ]
        ],
        [
            'name' => 'Software Engineering',
            'topics' => [
                ['name' => 'Software development life cycle (SDLC)'],
                ['name' => 'Requirements gathering and analysis'],
                ['name' => 'Design patterns and principles (e.g., MVC)'],
                ['name' => 'Version control systems (e.g., Git)'],
                ['name' => 'Agile methodologies (Scrum, Kanban)'],
            ]
        ],
        [
            'name' => 'Introduction to Web Development',
            'topics' => [
                ['name' => 'HTML, CSS, and JavaScript basics'],
                ['name' => 'Client-server architecture'],
                ['name' => 'Front-end frameworks (e.g., React, Angular)'],
                ['name' => 'Web APIs and AJAX'],
                ['name' => 'Responsive web design'],
            ]
        ],
        [
            'name' => 'Human-Computer Interaction (HCI)',
            'topics' => [
                ['name' => 'User interface design principles'],
                ['name' => 'Usability testing'],
                ['name' => 'Interaction design'],
                ['name' => 'Accessibility in design'],
                ['name' => 'Prototyping and wireframing tools'],
            ]
        ],
        [
            'name' => 'Cybersecurity Fundamentals',
            'topics' => [
                ['name' => 'Cryptography basics (symmetric and asymmetric encryption)'],
                ['name' => 'Authentication methods (passwords, biometrics)'],
                ['name' => 'Network security protocols (TLS, VPN)'],
                ['name' => 'Ethical hacking and penetration testing'],
                ['name' => 'Risk management and threat modeling'],
            ]
        ],
        [
            'name' => 'Mobile Application Development',
            'topics' => [
                ['name' => 'Mobile development platforms (Android, iOS)'],
                ['name' => 'UI design principles for mobile apps'],
                ['name' => 'Mobile app architecture (MVC, MVVM)'],
                ['name' => 'Mobile databases (SQLite, Firebase)'],
                ['name' => 'Integrating APIs in mobile apps'],
            ]
        ],
        [
            'name' => 'Theory of Computation',
            'topics' => [
                ['name' => 'Finite automata and regular expressions'],
                ['name' => 'Context-free grammars'],
                ['name' => 'Turing machines and decidability'],
                ['name' => 'Complexity classes (P, NP, NP-complete)'],
                ['name' => 'Formal languages and parsing techniques'],
            ]
        ],
        [
            'name' => 'Digital Logic Design',
            'topics' => [
                ['name' => 'Logic gates (AND, OR, NOT, XOR)'],
                ['name' => 'Boolean algebra and simplification'],
                ['name' => 'Combinational circuits (adders, multiplexers)'],
                ['name' => 'Sequential circuits (flip-flops, counters)'],
                ['name' => 'State machines and FSMs'],
            ]
        ],
        [
            'name' => 'Project Management for IT',
            'topics' => [
                ['name' => 'Project life cycle (initiation, planning, execution, closure)'],
                ['name' => 'Risk management in IT projects'],
                ['name' => 'Resource allocation and budgeting'],
                ['name' => 'Time management and scheduling (Gantt charts, Agile boards)'],
                ['name' => 'Team collaboration and communication'],
            ]
        ],
        [
            'name' => 'Unix/Linux Systems',
            'topics' => [
                ['name' => 'Unix/Linux command line basics'],
                ['name' => 'File system navigation and manipulation'],
                ['name' => 'Shell scripting and automation'],
                ['name' => 'Process management and signals'],
                ['name' => 'System administration basics (user management, permissions)'],
            ]
        ],
        [
            'name' => 'Software Testing and Debugging',
            'topics' => [
                ['name' => 'Types of software testing (unit, integration, system, acceptance)'],
                ['name' => 'Writing test cases and test scripts'],
                ['name' => 'Debugging techniques (breakpoints, logging)'],
                ['name' => 'Test-driven development (TDD)'],
                ['name' => 'Automation testing tools (JUnit, Selenium)'],
            ]
        ],
        [
            'name' => 'Programming Paradigms',
            'topics' => [
                ['name' => 'Procedural programming'],
                ['name' => 'Functional programming basics'],
                ['name' => 'Event-driven programming'],
                ['name' => 'Declarative programming'],
                ['name' => 'Multi-paradigm languages (e.g., Python, JavaScript)'],
            ]
        ],
        [
            'name' => 'Introduction to Machine Learning',
            'topics' => [
                ['name' => 'Overview of machine learning types (supervised, unsupervised, reinforcement learning)'],
                ['name' => 'Basic algorithms (linear regression, decision trees)'],
                ['name' => 'Data preprocessing and feature engineering'],
                ['name' => 'Model evaluation metrics'],
                ['name' => 'Overfitting and regularization'],
            ]
        ],
    ];
    
    public function run(): void
    {
        foreach ($this->subjects_with_topics as $subject_data) {
            $subject = Subject::factory()->create([
                'course_id' =>  Course::find(1)->id,
                'name' => $subject_data['name'],
            ]);
            foreach ($subject_data['topics'] as $topic_data) {
                $subject->topics()->create($topic_data);
            }
        }
        Question::factory()->count(200)->create();
    }
}
