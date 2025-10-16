<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\News;
use App\Models\User;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first admin user or create a system user
        $admin = User::where('is_admin', true)->first();
        
        if (!$admin) {
            $admin = User::where('email', 'system+anonymized@fvn.li')->first();
        }

        if (!$admin) {
            // If no admin exists, skip seeding
            $this->command->warn('No admin user found. Skipping news seeding.');
            return;
        }

        // Create sample news items
        News::create([
            'title' => 'itch.io Login Functionality Restored',
            'slug' => 'itchio-login-functionality-restored',
            'content' => '<p>We are pleased to announce that the itch.io login functionality has been fully restored and is now working as expected.</p>
<p><strong>What happened:</strong></p>
<ul>
<li>Earlier today, we experienced technical difficulties with the itch.io authentication integration</li>
<li>This prevented users from logging in using their itch.io accounts</li>
<li>The issue has been identified and resolved</li>
</ul>
<p><strong>Important Notice - User Data Restore:</strong></p>
<p>Due to the nature of the incident, we had to perform an emergency user record restore. Unfortunately, this means that <strong>any user accounts created today have been lost</strong>. We sincerely apologize for this inconvenience.</p>
<p>If you registered today, you will need to create your account again. We have implemented additional safeguards to prevent similar issues in the future.</p>
<p>Thank you for your patience and understanding.</p>',
            'type' => News::TYPE_INCIDENT,
            'is_published' => true,
            'published_at' => now(),
            'author_id' => $admin->id,
        ]);

        News::create([
            'title' => 'Welcome to FVN.li News',
            'slug' => 'welcome-to-fvnli-news',
            'content' => '<p>Welcome to the FVN.li News & Announcements page!</p>
<p>This is your central hub for staying informed about:</p>
<ul>
<li><strong>Site Updates:</strong> New features, improvements, and enhancements</li>
<li><strong>Announcements:</strong> Important information about the platform</li>
<li><strong>Maintenance:</strong> Scheduled maintenance windows and system updates</li>
<li><strong>Incidents:</strong> Transparency about any issues affecting the service</li>
</ul>
<p>We are committed to keeping you informed about everything happening with FVN.li. Check back regularly for the latest updates!</p>',
            'type' => News::TYPE_ANNOUNCEMENT,
            'is_published' => true,
            'published_at' => now()->subDay(),
            'author_id' => $admin->id,
        ]);

        $this->command->info('News items seeded successfully!');
    }
}

