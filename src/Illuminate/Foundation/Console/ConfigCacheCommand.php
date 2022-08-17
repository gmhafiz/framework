<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'config:cache')]
class ConfigCacheCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:cache';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'config:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster configuration loading';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new config cache command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function handle()
    {
        if ($this->hasEnv())
        {
            exit();
        }

        $this->call('config:clear');

        $config = $this->getFreshConfiguration();

        $configPath = $this->laravel->getCachedConfigPath();

        $this->files->put(
            $configPath, '<?php return '.var_export($config, true).';'.PHP_EOL
        );

        try {
            require $configPath;
        } catch (Throwable $e) {
            $this->files->delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        $this->components->info('Configuration cached successfully.');
    }

    /**
     * Boot a fresh copy of the application configuration.
     *
     * @return array
     */
    protected function getFreshConfiguration()
    {
        $app = require $this->laravel->bootstrapPath().'/app.php';

        $app->useStoragePath($this->laravel->storagePath());

        $app->make(ConsoleKernelContract::class)->bootstrap();

        return $app['config']->all();
    }

    private function hasEnv(): bool
    {
        $root = $this->laravel->basePath();

        $dir = $root.'/app/';
        $re = '/env\(.*\)/m';
        $results = [];
        $files = File::allFiles($dir);

        foreach ($files as $file)
        {
            $content = file_get_contents($dir.$file->getRelativePathname());

            if (!$content)
            {
                continue;
            }

            $found = count(preg_grep($re, [$content]));
            if ($found == 0) {
                continue;
            }


            $results[] = [
                'file' => $file,
            ];
        }

        if (count($results) == 0)
        {
            return false;
        }

        $this->warn('env() exists in:');
        $this->newLine();
        foreach ($results as $result)
        {
            $this->warn($result['file']);
        }

        $this->newLine();
        $this->warn('Please remove your env() outside of config directory if you want to use config:cache');

        return true;
    }
}
