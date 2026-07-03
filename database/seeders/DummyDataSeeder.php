<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\ProjectStudent;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    private int $studentCounter = 0;

    public function run(): void
    {
        DB::transaction(function () {
            $departments     = $this->createDepartments();
            $specializations = $this->createSpecializations($departments);
            [$managers, $supervisors, $staffs] = $this->createUsers($departments);
            $examiners    = $this->createExaminers($departments);
            $projectCount = $this->createProjects($departments, $specializations, $supervisors, $managers, $examiners);

            $specCount = array_sum(array_map('count', $specializations));
            $userCount = count($managers) + count($supervisors) + count($staffs);
            $examCount = array_sum(array_map('count', $examiners));

            $this->command->info(sprintf(
                "Created: %d departments, %d specializations, %d users, %d examiners, %d projects",
                count($departments),
                $specCount,
                $userCount,
                $examCount,
                $projectCount
            ));
        });
    }

    private function createDepartments(): array
    {
        $data = [
            ['name' => 'Software Engineering',   'code' => 'SW',   'description' => 'قسم هندسة البرمجيات والتطبيقات'],
            ['name' => 'Networks Engineering',    'code' => 'NET',  'description' => 'قسم هندسة الشبكات والاتصالات'],
            ['name' => 'Electronics Engineering', 'code' => 'ELEC', 'description' => 'قسم هندسة الإلكترونيات والدوائر'],
        ];

        $depts = [];
        foreach ($data as $d) {
            $depts[$d['code']] = Department::create($d);
        }
        return $depts;
    }

    private function createSpecializations(array $departments): array
    {
        $data = [
            'SW'   => ['Web Development', 'Mobile Development', 'AI & Machine Learning', 'Database Systems'],
            'NET'  => ['Network Security', 'Cloud Computing', 'Wireless Networks'],
            'ELEC' => ['Embedded Systems', 'Digital Circuits', 'Power Electronics'],
        ];

        $specs = [];
        foreach ($data as $code => $names) {
            $specs[$code] = [];
            foreach ($names as $name) {
                $specs[$code][$name] = Specialization::create([
                    'department_id' => $departments[$code]->id,
                    'name'          => $name,
                ]);
            }
        }
        return $specs;
    }

    private function createUsers(array $departments): array
    {
        $pass = Hash::make('password');

        $managerDefs = [
            'SW'   => ['name' => 'أحمد محمد العمري',      'email' => 'manager.sw@college.com'],
            'NET'  => ['name' => 'خالد إبراهيم الزهراني', 'email' => 'manager.net@college.com'],
            'ELEC' => ['name' => 'سعد عبدالله الغامدي',   'email' => 'manager.elec@college.com'],
        ];

        $managers = [];
        foreach ($managerDefs as $code => $def) {
            $u = User::create([
                'name'              => $def['name'],
                'email'             => $def['email'],
                'password'          => $pass,
                'email_verified_at' => now(),
                'department_id'     => $departments[$code]->id,
                'is_active'         => true,
            ]);
            $u->assignRole('dept_manager');
            $managers[$code] = $u;
        }

        $supervisorDefs = [
            'SW'   => [
                ['name' => 'محمد علي الشهري',        'email' => 'supervisor1.sw@college.com'],
                ['name' => 'عمر حسن الدوسري',        'email' => 'supervisor2.sw@college.com'],
            ],
            'NET'  => [
                ['name' => 'فيصل أحمد القحطاني',     'email' => 'supervisor1.net@college.com'],
                ['name' => 'عبدالرحمن يوسف العتيبي', 'email' => 'supervisor2.net@college.com'],
            ],
            'ELEC' => [
                ['name' => 'نايف سعد المالكي',       'email' => 'supervisor1.elec@college.com'],
                ['name' => 'طارق محمد البقمي',       'email' => 'supervisor2.elec@college.com'],
            ],
        ];

        $supervisors = [];
        foreach ($supervisorDefs as $code => $defs) {
            $supervisors[$code] = [];
            foreach ($defs as $def) {
                $u = User::create([
                    'name'              => $def['name'],
                    'email'             => $def['email'],
                    'password'          => $pass,
                    'email_verified_at' => now(),
                    'department_id'     => $departments[$code]->id,
                    'is_active'         => true,
                ]);
                $u->assignRole('supervisor');
                $supervisors[$code][] = $u;
            }
        }

        $staffDefs = [
            ['name' => 'ريم سلمان الجهني',   'email' => 'staff1@college.com', 'dept' => 'SW'],
            ['name' => 'هند عبدالله الحربي', 'email' => 'staff2@college.com', 'dept' => 'SW'],
            ['name' => 'بندر خالد الرشيدي',  'email' => 'staff3@college.com', 'dept' => 'NET'],
            ['name' => 'منى حمد الثقفي',     'email' => 'staff4@college.com', 'dept' => 'ELEC'],
            ['name' => 'وليد محمد الشمري',   'email' => 'staff5@college.com', 'dept' => 'ELEC'],
        ];

        $staffs = [];
        foreach ($staffDefs as $def) {
            $u = User::create([
                'name'              => $def['name'],
                'email'             => $def['email'],
                'password'          => $pass,
                'email_verified_at' => now(),
                'department_id'     => $departments[$def['dept']]->id,
                'is_active'         => true,
            ]);
            $u->assignRole('dept_staff');
            $staffs[] = $u;
        }

        return [$managers, $supervisors, $staffs];
    }

    private function createExaminers(array $departments): array
    {
        $data = [
            'SW'   => [
                ['full_name' => 'د. حمد سليمان الفيفي',      'title' => 'Dr.'],
                ['full_name' => 'أ.د. عبدالعزيز محمد الزيد', 'title' => 'Prof.'],
                ['full_name' => 'د. منيرة خالد العنزي',       'title' => 'Dr.'],
            ],
            'NET'  => [
                ['full_name' => 'د. يوسف إبراهيم المسعد',    'title' => 'Dr.'],
                ['full_name' => 'أ.د. راشد سعد الوادعي',     'title' => 'Prof.'],
                ['full_name' => 'د. نورة فهد الصالح',         'title' => 'Dr.'],
            ],
            'ELEC' => [
                ['full_name' => 'د. بدر عبدالرحمن الحسيني',  'title' => 'Dr.'],
                ['full_name' => 'أ.د. صالح مطلق الجعيد',     'title' => 'Prof.'],
            ],
        ];

        $examiners = [];
        foreach ($data as $code => $items) {
            $examiners[$code] = [];
            foreach ($items as $item) {
                $examiners[$code][] = Examiner::create([
                    'full_name'     => $item['full_name'],
                    'title'         => $item['title'],
                    'department_id' => $departments[$code]->id,
                ]);
            }
        }
        return $examiners;
    }

    private function createProjects(
        array $departments,
        array $specializations,
        array $supervisors,
        array $managers,
        array $examiners
    ): int {
        $projectDefs = [
            // Software Engineering — Web Development
            ['title' => 'نظام إدارة المكتبة الإلكترونية',                        'dept' => 'SW', 'spec' => 'Web Development',       'year' => '2023/2024', 'status' => 1, 'score' => 87.50, 'visits' => 134],
            ['title' => 'منصة التجارة الإلكترونية للمنتجات المحلية',              'dept' => 'SW', 'spec' => 'Web Development',       'year' => '2024/2025', 'status' => 1, 'score' => 91.00, 'visits' => 98],
            ['title' => 'بوابة التعليم الإلكتروني للمدارس الثانوية',              'dept' => 'SW', 'spec' => 'Web Development',       'year' => '2025/2026', 'status' => 5, 'score' => null,  'visits' => 23],
            // Software Engineering — Mobile Development
            ['title' => 'تطبيق متابعة النشاط البدني للرياضيين',                   'dept' => 'SW', 'spec' => 'Mobile Development',    'year' => '2023/2024', 'status' => 1, 'score' => 78.50, 'visits' => 57],
            ['title' => 'تطبيق إدارة الأدوية للمرضى المزمنين',                   'dept' => 'SW', 'spec' => 'Mobile Development',    'year' => '2024/2025', 'status' => 1, 'score' => null,  'visits' => 12],
            // Software Engineering — AI & Machine Learning
            ['title' => 'نظام التعرف على الوجوه لتسجيل حضور الطلاب',             'dept' => 'SW', 'spec' => 'AI & Machine Learning', 'year' => '2024/2025', 'status' => 1, 'score' => 94.00, 'visits' => 147],
            ['title' => 'تحليل مشاعر النصوص العربية على وسائل التواصل الاجتماعي', 'dept' => 'SW', 'spec' => 'AI & Machine Learning', 'year' => '2025/2026', 'status' => 1, 'score' => 82.75, 'visits' => 63],
            // Software Engineering — Database Systems
            ['title' => 'نظام قاعدة بيانات موزعة للمستشفيات',                    'dept' => 'SW', 'spec' => 'Database Systems',      'year' => '2023/2024', 'status' => 1, 'score' => 88.00, 'visits' => 41],
            ['title' => 'تحسين أداء قواعد البيانات الضخمة في بيئات الإنتاج',    'dept' => 'SW', 'spec' => 'Database Systems',      'year' => '2026/2027', 'status' => 2, 'score' => null,  'visits' => 5],
            // Networks Engineering — Network Security
            ['title' => 'نظام كشف التسلل إلى الشبكات المحلية',                   'dept' => 'NET', 'spec' => 'Network Security',   'year' => '2023/2024', 'status' => 1, 'score' => 89.50, 'visits' => 112],
            ['title' => 'تشفير الاتصالات في الشبكات اللاسلكية',                   'dept' => 'NET', 'spec' => 'Network Security',   'year' => '2024/2025', 'status' => 1, 'score' => 76.00, 'visits' => 88],
            ['title' => 'مراقبة الثغرات الأمنية في البنية التحتية للشبكات',       'dept' => 'NET', 'spec' => 'Network Security',   'year' => '2025/2026', 'status' => 1, 'score' => 83.25, 'visits' => 35],
            // Networks Engineering — Cloud Computing
            ['title' => 'نظام النسخ الاحتياطي السحابي للشركات الصغيرة',          'dept' => 'NET', 'spec' => 'Cloud Computing',    'year' => '2023/2024', 'status' => 1, 'score' => 90.00, 'visits' => 79],
            ['title' => 'منصة الحوسبة السحابية للتطبيقات الحكومية',              'dept' => 'NET', 'spec' => 'Cloud Computing',    'year' => '2024/2025', 'status' => 1, 'score' => null,  'visits' => 18],
            ['title' => 'تحسين أداء الشبكات السحابية الموزعة',                   'dept' => 'NET', 'spec' => 'Cloud Computing',    'year' => '2025/2026', 'status' => 5, 'score' => null,  'visits' => 8],
            // Networks Engineering — Wireless Networks
            ['title' => 'تحسين تغطية شبكات الواي فاي في المباني الجامعية',       'dept' => 'NET', 'spec' => 'Wireless Networks',  'year' => '2024/2025', 'status' => 1, 'score' => 85.50, 'visits' => 66],
            ['title' => 'نظام مراقبة الشبكات اللاسلكية في الوقت الفعلي',        'dept' => 'NET', 'spec' => 'Wireless Networks',  'year' => '2023/2024', 'status' => 1, 'score' => 79.00, 'visits' => 52],
            // Electronics Engineering — Embedded Systems
            ['title' => 'نظام إدارة البيت الذكي باستخدام الميكروكنترولر',         'dept' => 'ELEC', 'spec' => 'Embedded Systems',  'year' => '2023/2024', 'status' => 1, 'score' => 92.50, 'visits' => 139],
            ['title' => 'روبوت التنقل الذاتي في البيئات الداخلية',               'dept' => 'ELEC', 'spec' => 'Embedded Systems',  'year' => '2024/2025', 'status' => 1, 'score' => 86.00, 'visits' => 73],
            ['title' => 'نظام مراقبة الطاقة الشمسية في المناطق النائية',         'dept' => 'ELEC', 'spec' => 'Embedded Systems',  'year' => '2025/2026', 'status' => 6, 'score' => null,  'visits' => 30],
            // Electronics Engineering — Digital Circuits
            ['title' => 'تصميم دائرة ترميز رقمي للإشارات الصوتية',               'dept' => 'ELEC', 'spec' => 'Digital Circuits',  'year' => '2023/2024', 'status' => 1, 'score' => 80.50, 'visits' => 45],
            ['title' => 'وحدة معالجة منطقية قابلة للبرمجة للتطبيقات الصناعية',  'dept' => 'ELEC', 'spec' => 'Digital Circuits',  'year' => '2024/2025', 'status' => 1, 'score' => null,  'visits' => 15],
            ['title' => 'نظام معالجة الإشارات الرقمية للاتصالات الضوئية',        'dept' => 'ELEC', 'spec' => 'Digital Circuits',  'year' => '2025/2026', 'status' => 1, 'score' => 88.75, 'visits' => 58],
            // Electronics Engineering — Power Electronics
            ['title' => 'نظام شحن البطاريات الذكية للسيارات الكهربائية',         'dept' => 'ELEC', 'spec' => 'Power Electronics', 'year' => '2024/2025', 'status' => 1, 'score' => 93.00, 'visits' => 110],
            ['title' => 'محول الطاقة الشمسية عالي الكفاءة للشبكات الكهربائية',  'dept' => 'ELEC', 'spec' => 'Power Electronics', 'year' => '2023/2024', 'status' => 1, 'score' => 85.00, 'visits' => 96],
        ];

        $comments = [
            'مشروع متميز يُظهر فهماً عميقاً للموضوع مع تطبيق عملي احترافي',
            'توثيق ممتاز وتطبيق عملي متقن يعكس جهداً بحثياً حقيقياً',
            'البنية التقنية جيدة وتحتاج مزيداً من التحسين في الأداء',
            'تصميم مبتكر مع منهجية بحثية واضحة وأدلة نتائج موثوقة',
            'المشروع يعالج مشكلة واقعية بأسلوب علمي متميز ومنظم',
            'النتائج مقنعة والخوارزمية المُقترحة فعّالة وقابلة للتطوير',
            'يُوصى بنشر هذا العمل في المؤتمرات العلمية المتخصصة',
            'التطبيق العملي يعمل بكفاءة عالية في بيئات متعددة',
            'الأداء التقني جيد ويستلزم مزيداً من التحسينات المستقبلية',
            'العمل الجماعي واضح في جودة التنفيذ والاتساق المنهجي',
        ];

        $arabicNames = [
            'أحمد سعد العمري',      'محمد علي السلمي',      'عبدالله خالد القحطاني',
            'سلطان فهد الغامدي',    'ماجد يوسف الشهري',    'فيصل حمد الدوسري',
            'نواف إبراهيم الزهراني','بدر سليمان المالكي',   'وليد عمر العتيبي',
            'ياسر محمد الجهني',     'عمر سعيد الرشيدي',    'تركي عبدالرحمن البقمي',
            'خالد ناصر الشمري',     'سعود علي الحربي',     'عبدالعزيز فهد الثقفي',
            'سارة أحمد الزهراني',   'نورة محمد العنزي',    'منيرة خالد الفيفي',
            'هند عبدالله المسعد',   'ريم سلمان الوادعي',
        ];

        // Build all possible examiner pairs per department for rotation
        $examinerPairs = [];
        foreach ($examiners as $code => $deptExaminers) {
            $pairs = [];
            $n = count($deptExaminers);
            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    $pairs[] = [$a, $b];
                }
            }
            $examinerPairs[$code] = $pairs;
        }

        $pairCounters = ['SW' => 0, 'NET' => 0, 'ELEC' => 0];
        $supvCounters = ['SW' => 0, 'NET' => 0, 'ELEC' => 0];
        $createdProjects = [];

        foreach ($projectDefs as $def) {
            $code = $def['dept'];
            $dept = $departments[$code];
            $spec = $specializations[$code][$def['spec']];

            $supervisor = $supervisors[$code][$supvCounters[$code]++ % 2];

            $project = Project::create([
                'project_title'     => $def['title'],
                'description'       => $this->specDescription($def['spec']),
                'academic_year'     => $def['year'],
                'department_id'     => $dept->id,
                'specialization_id' => $spec->id,
                'supervisor_id'     => $supervisor->id,
                'current_status_id' => $def['status'],
                'final_score'       => $def['score'],
                'visit_count'       => $def['visits'],
                'is_deleted'        => false,
            ]);

            // 2–4 students per project
            $studentCount = rand(2, 4);
            $yearPrefix   = substr($def['year'], 0, 4);
            for ($s = 0; $s < $studentCount; $s++) {
                $this->studentCounter++;
                ProjectStudent::create([
                    'project_id'          => $project->id,
                    'full_name'           => $arabicNames[$this->studentCounter % count($arabicNames)],
                    'registration_number' => $yearPrefix . str_pad($this->studentCounter, 5, '0', STR_PAD_LEFT),
                    'status'              => 'active',
                ]);
            }

            // Assign 2 examiners + evaluations for scored projects
            if ($def['score'] !== null) {
                $pairs = $examinerPairs[$code];
                $pair  = $pairs[$pairCounters[$code]++ % count($pairs)];
                $ex1   = $examiners[$code][$pair[0]];
                $ex2   = $examiners[$code][$pair[1]];

                DB::table('project_examiners')->insert([
                    ['project_id' => $project->id, 'examiner_id' => $ex1->id, 'assigned_by' => $managers[$code]->id, 'created_at' => now(), 'updated_at' => now()],
                    ['project_id' => $project->id, 'examiner_id' => $ex2->id, 'assigned_by' => $managers[$code]->id, 'created_at' => now(), 'updated_at' => now()],
                ]);

                Evaluation::create(['project_id' => $project->id, 'examiner_id' => $ex1->id, 'notes' => $comments[array_rand($comments)]]);
                Evaluation::create(['project_id' => $project->id, 'examiner_id' => $ex2->id, 'notes' => $comments[array_rand($comments)]]);
            }

            $createdProjects[] = $project;
        }

        // 3 project evolutions: link based_on_project_id
        // index 6 (تحليل المشاعر) evolves from index 5 (التعرف على الوجوه) — both SW AI
        $createdProjects[6]->update(['based_on_project_id' => $createdProjects[5]->id]);
        // index 8 (تحسين أداء DB) evolves from index 7 (DB الموزعة) — both SW Database
        $createdProjects[8]->update(['based_on_project_id' => $createdProjects[7]->id]);
        // index 22 (معالجة الإشارات) evolves from index 20 (دائرة الترميز) — both ELEC Digital
        $createdProjects[22]->update(['based_on_project_id' => $createdProjects[20]->id]);

        return count($createdProjects);
    }

    private function specDescription(string $specName): string
    {
        return match ($specName) {
            'Web Development'       => 'مشروع تخرج يهدف إلى تطوير تطبيق ويب متكامل باستخدام أحدث التقنيات والأطر البرمجية لحل مشكلة عملية محددة',
            'Mobile Development'    => 'مشروع تخرج يهدف إلى تطوير تطبيق جوال متعدد المنصات يوفر تجربة مستخدم سلسة وفعالة',
            'AI & Machine Learning' => 'مشروع تخرج يستخدم تقنيات الذكاء الاصطناعي والتعلم الآلي لتطوير نظام ذكي قادر على التعلم والتكيف',
            'Database Systems'      => 'مشروع تخرج يركز على تصميم وتحسين أنظمة قواعد البيانات لمعالجة البيانات الضخمة بكفاءة عالية',
            'Network Security'      => 'مشروع تخرج يهدف إلى تعزيز أمان الشبكات وحمايتها من التهديدات الإلكترونية المتطورة',
            'Cloud Computing'       => 'مشروع تخرج يستكشف تقنيات الحوسبة السحابية لتوفير حلول مرنة وقابلة للتوسع للمؤسسات',
            'Wireless Networks'     => 'مشروع تخرج يبحث في تحسين أداء الشبكات اللاسلكية وتوسيع نطاق تغطيتها في البيئات المختلفة',
            'Embedded Systems'      => 'مشروع تخرج يتناول تصميم وبرمجة الأنظمة المدمجة للتطبيقات الصناعية والاستهلاكية الذكية',
            'Digital Circuits'      => 'مشروع تخرج يتعمق في تصميم الدوائر الرقمية المتكاملة لمعالجة الإشارات وتنفيذ العمليات المنطقية',
            'Power Electronics'     => 'مشروع تخرج يركز على تصميم أنظمة إلكترونيات الطاقة لتحسين كفاءة تحويل الطاقة وتوزيعها',
            default                 => 'مشروع تخرج تطبيقي في مجال التخصص',
        };
    }
}
