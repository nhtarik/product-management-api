<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;

class MakeFullApiResource extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-full-api-resource
                            {name : The name of the resource}
                            {--factory : Also create a model factory}
                            {--seeder : Also create a database seeder}
                            {--resource : Also create an API resource class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a full API resource: model, migration, controller, requests and optionally resource, factory, seeder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $tableName = Str::snake(Str::pluralStudly($this->argument('name')));

        $this->info("Creating full API resource for: {$name}");


        // Create Model + Migration
        $this->call('make:model', [
            'name'  => $name,
            '-m'    => true
        ]);

        $this->line("Model and migration created: app/Models/{$name}.php");



        // Create API Controller with Request
        $controllerPath = "API/{$name}Controller";
        $this->call('make:controller', [
            'name'          => $controllerPath,
            '--model'       => $name,
            '--api'         => true,
            '--requests'    => true,
        ]);

        $this->line("API Controller created: app/Http/Controllers/API/{$name}Controller.php");



        // Create Resource (Optional)
        if ($this->option('resource')) {
            $this->call('make:resource', [
                'name' => "{$name}Resource"
            ]);

            $this->line("Resource created: app/Http/Resources/{$name}Resource.php");
        } else {
            $this->warn("Skipped resource creation");
        }



        // Create Factory (Optional)
        if ($this->option('factory')) {
            $this->call('make:factory', [
                'name'      => "{$name}Factory",
                '--model'   => "App\Models\\{$name}"
            ]);

            $this->line("Seeder created: database/seeders/{$name}Seeder.php");
        } else {
            $this->warn("Skipped resource creation");
        }



        // Create Seeder (optional)
        if ($this->option('seeder')) {
            $this->call('make:seeder', [
                'name' => "{$name}Seeder",
            ]);
            $this->line("Seeder created: database/seeders/{$name}Seeder.php");
        } else {
            $this->warn("Skipped seeder creation");
        }



        // 6️⃣ Final summary
        $this->newLine();
        $this->info("🎉 Full API resource for '{$name}' created successfully!");
        $this->line("Next steps:");
        $this->line("➡️ Edit migration file: database/migrations/*_create_{$tableName}_table.php");
        $this->line("➡️ Run migrations: php artisan migrate");
        $this->line("➡️ Register routes in: routes/api.php");
    }
}
