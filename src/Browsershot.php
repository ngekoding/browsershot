<?php

namespace Ngekoding\Browsershot;

use Ngekoding\Browsershot\Exceptions\CouldNotTakeBrowsershot;
use Ngekoding\Browsershot\Exceptions\ElementNotFound;
use Ngekoding\Browsershot\Exceptions\UnavailableFeature;
// use Spatie\Image\Image;
// use Spatie\Image\Manipulations;
// use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** @mixin \Spatie\Image\Manipulations */
class Browsershot
{
    protected $nodeBinary = null;
    protected $npmBinary = null;
    protected $nodeModulePath = null;
    protected $includePath = '$PATH:/usr/local/bin';
    protected $binPath = null;
    protected $html = '';
    protected $noSandbox = false;
    protected $proxyServer = '';
    protected $showBackground = false;
    protected $showScreenshotBackground = true;
    protected $screenshotType = 'png';
    protected $screenshotQuality = null;
    protected $temporaryHtmlDirectory;
    protected $timeout = 60;
    protected $url = '';
    protected $additionalOptions = [];
    protected $temporaryOptionsDirectory;
    protected $writeOptionsToFile = false;
    protected $chromiumArguments = [];

    /** @var \Spatie\Image\Manipulations */
    protected $imageManipulations;

    const POLLING_REQUEST_ANIMATION_FRAME = 'raf';
    const POLLING_MUTATION = 'mutation';

    /**
     * @param $url
     *
     * @return static
     */
    public static function url($url)
    {
        return (new static)->setUrl($url);
    }

    /**
     * @param $html
     *
     * @return static
     */
    public static function html($html)
    {
        return (new static)->setHtml($html);
    }

    public function __construct($url = '', $deviceEmulate = false)
    {
        $this->url = $url;

        // $this->imageManipulations = new Manipulations();

        if (! $deviceEmulate) {
            $this->windowSize(800, 600);
        }
    }

    public function setNodeBinary($nodeBinary)
    {
        $this->nodeBinary = $nodeBinary;

        return $this;
    }

    public function setNpmBinary($npmBinary)
    {
        $this->npmBinary = $npmBinary;

        return $this;
    }

    public function setIncludePath($includePath)
    {
        $this->includePath = $includePath;

        return $this;
    }

    public function setBinPath($binPath)
    {
        $this->binPath = $binPath;

        return $this;
    }

    public function setNodeModulePath($nodeModulePath)
    {
        $this->nodeModulePath = $nodeModulePath;

        return $this;
    }

    public function setChromePath($executablePath)
    {
        $this->setOption('executablePath', $executablePath);

        return $this;
    }

    public function useCookies(array $cookies, $domain = null)
    {
        if (! count($cookies)) {
            return $this;
        }

        if (is_null($domain)) {
            $domain = parse_url($this->url)['host'];
        }

        $cookies = array_map(function ($value, $name) use ($domain) {
            return compact('name', 'value', 'domain');
        }, $cookies, array_keys($cookies));

        if (isset($this->additionalOptions['cookies'])) {
            $cookies = array_merge($this->additionalOptions['cookies'], $cookies);
        }

        $this->setOption('cookies', $cookies);

        return $this;
    }

    public function setExtraHttpHeaders(array $extraHTTPHeaders)
    {
        $this->setOption('extraHTTPHeaders', $extraHTTPHeaders);

        return $this;
    }

    public function authenticate($username, $password)
    {
        $this->setOption('authentication', compact('username', 'password'));

        return $this;
    }

    public function click($selector, $button = 'left', $clickCount = 1, $delay = 0)
    {
        $clicks = $this->additionalOptions['clicks'] ? $this->additionalOptions['clicks'] : [];

        $clicks[] = compact('selector', 'button', 'clickCount', 'delay');

        $this->setOption('clicks', $clicks);

        return $this;
    }

    public function selectOption($selector, $value = '')
    {
        $dropdownSelects = $this->additionalOptions['selects'] ? $this->additionalOptions['selects'] : [];

        $dropdownSelects[] = compact('selector', 'value');

        $this->setOption('selects', $dropdownSelects);

        return $this;
    }

    public function type($selector, $text = '', $delay = 0)
    {
        $types = $this->additionalOptions['types'] ? $this->additionalOptions['types'] : [];

        $types[] = compact('selector', 'text', 'delay');

        $this->setOption('types', $types);

        return $this;
    }

    /**
     * @deprecated This option is no longer supported by modern versions of Puppeteer.
     */
    public function setNetworkIdleTimeout($networkIdleTimeout)
    {
        $this->setOption('networkIdleTimeout');

        return $this;
    }

    public function waitUntilNetworkIdle($strict = true)
    {
        $this->setOption('waitUntil', $strict ? 'networkidle0' : 'networkidle2');

        return $this;
    }

    public function waitForFunction($function, $polling = self::POLLING_REQUEST_ANIMATION_FRAME, $timeout = 0)
    {
        $this->setOption('functionPolling', $polling);
        $this->setOption('functionTimeout', $timeout);

        return $this->setOption('function', $function);
    }

    public function setUrl($url)
    {
        $this->url = $url;
        $this->html = '';

        return $this;
    }

    public function setProxyServer($proxyServer)
    {
        $this->proxyServer = $proxyServer;

        return $this;
    }

    public function setHtml($html)
    {
        $this->html = $html;
        $this->url = '';

        $this->hideBrowserHeaderAndFooter();

        return $this;
    }

    public function clip($x, $y, $width, $height)
    {
        return $this->setOption('clip', compact('x', 'y', 'width', 'height'));
    }

    public function select($selector)
    {
        return $this->setOption('selector', $selector);
    }

    public function showBrowserHeaderAndFooter()
    {
        return $this->setOption('displayHeaderFooter', true);
    }

    public function hideBrowserHeaderAndFooter()
    {
        return $this->setOption('displayHeaderFooter', false);
    }

    public function hideHeader()
    {
        return $this->headerHtml('<p></p>');
    }

    public function hideFooter()
    {
        return $this->footerHtml('<p></p>');
    }

    public function headerHtml($html)
    {
        return $this->setOption('headerTemplate', $html);
    }

    public function footerHtml($html)
    {
        return $this->setOption('footerTemplate', $html);
    }

    public function deviceScaleFactor($deviceScaleFactor)
    {
        // Google Chrome currently supports values of 1, 2, and 3.
        return $this->setOption('viewport.deviceScaleFactor', max(1, min(3, $deviceScaleFactor)));
    }

    public function fullPage()
    {
        return $this->setOption('fullPage', true);
    }

    public function showBackground()
    {
        $this->showBackground = true;
        $this->showScreenshotBackground = true;

        return $this;
    }

    public function hideBackground()
    {
        $this->showBackground = false;
        $this->showScreenshotBackground = false;

        return $this;
    }

    public function setScreenshotType($type, $quality = null)
    {
        $this->screenshotType = $type;

        if (! is_null($quality)) {
            $this->screenshotQuality = $quality;
        }

        return $this;
    }

    public function ignoreHttpsErrors()
    {
        return $this->setOption('ignoreHttpsErrors', true);
    }

    public function mobile($mobile = true)
    {
        return $this->setOption('viewport.isMobile', true);
    }

    public function touch($touch = true)
    {
        return $this->setOption('viewport.hasTouch', true);
    }

    public function landscape($landscape = true)
    {
        return $this->setOption('landscape', $landscape);
    }

    public function margins($top, $right, $bottom, $left, $unit = 'mm')
    {
        return $this->setOption('margin', [
            'top' => $top.$unit,
            'right' => $right.$unit,
            'bottom' => $bottom.$unit,
            'left' => $left.$unit,
        ]);
    }

    public function noSandbox()
    {
        $this->noSandbox = true;

        return $this;
    }

    public function dismissDialogs()
    {
        return $this->setOption('dismissDialogs', true);
    }

    public function disableJavascript()
    {
        return $this->setOption('disableJavascript', true);
    }

    public function disableImages()
    {
        return $this->setOption('disableImages', true);
    }

    public function blockUrls($array)
    {
        return $this->setOption('blockUrls', json_encode($array));
    }

    public function blockDomains($array)
    {
        return $this->setOption('blockDomains', json_encode($array));
    }

    public function pages($pages)
    {
        return $this->setOption('pageRanges', $pages);
    }

    public function paperSize($width, $height, $unit = 'mm')
    {
        return $this
            ->setOption('width', $width.$unit)
            ->setOption('height', $height.$unit);
    }

    // paper format
    public function format($format)
    {
        return $this->setOption('format', $format);
    }

    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        $this->setOption('timeout', $timeout * 1000);

        return $this;
    }

    public function userAgent($userAgent)
    {
        $this->setOption('userAgent', $userAgent);

        return $this;
    }

    public function device($device)
    {
        $this->setOption('device', $device);

        return $this;
    }

    public function emulateMedia($media)
    {
        $this->setOption('emulateMedia', $media);

        return $this;
    }

    public function windowSize($width, $height)
    {
        return $this
            ->setOption('viewport.width', $width)
            ->setOption('viewport.height', $height);
    }

    public function setDelay($delayInMilliseconds)
    {
        return $this->setOption('delay', $delayInMilliseconds);
    }

    public function delay($delayInMilliseconds)
    {
        return $this->setDelay($delayInMilliseconds);
    }

    public function writeOptionsToFile()
    {
        $this->writeOptionsToFile = true;

        return $this;
    }

    public function setOption($key, $value)
    {
        $this->arraySet($this->additionalOptions, $key, $value);

        return $this;
    }

    public function addChromiumArguments(array $arguments)
    {
        foreach ($arguments as $argument => $value) {
            if (is_numeric($argument)) {
                $this->chromiumArguments[] = "--$value";
            } else {
                $this->chromiumArguments[] = "--$argument=$value";
            }
        }

        return $this;
    }

    public function __call($name, $arguments)
    {
        $this->imageManipulations->$name(...$arguments);

        return $this;
    }

    public function save($targetPath)
    {
        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

        if ($extension === '') {
            throw CouldNotTakeBrowsershot::outputFileDidNotHaveAnExtension($targetPath);
        }

        if ($extension === 'pdf') {
            return $this->savePdf($targetPath);
        } else {
            throw new UnavailableFeature($extension);
        }

        $command = $this->createScreenshotCommand($targetPath);

        $this->callBrowser($command);

        $this->cleanupTemporaryHtmlFile();

        if (! file_exists($targetPath)) {
            throw CouldNotTakeBrowsershot::chromeOutputEmpty($targetPath);
        }

        if (! $this->imageManipulations->isEmpty()) {
            $this->applyManipulations($targetPath);
        }
    }

    public function bodyHtml()
    {
        $command = $this->createBodyHtmlCommand();

        return $this->callBrowser($command);
    }

    public function screenshot()
    {
        throw new UnavailableFeature('screenshot');

        if ($this->imageManipulations->isEmpty()) {
            $command = $this->createScreenshotCommand();

            $encoded_image = $this->callBrowser($command);

            return base64_decode($encoded_image);
        }

        $temporaryDirectory = (new TemporaryDirectory())->create();

        $this->save($temporaryDirectory->path('screenshot.png'));

        $screenshot = file_get_contents($temporaryDirectory->path('screenshot.png'));

        $temporaryDirectory->delete();

        return $screenshot;
    }

    public function pdf()
    {
        $command = $this->createPdfCommand();

        $encoded_pdf = $this->callBrowser($command);

        $this->cleanupTemporaryHtmlFile();

        return base64_decode($encoded_pdf);
    }

    public function savePdf($targetPath)
    {
        $command = $this->createPdfCommand($targetPath);

        $this->callBrowser($command);

        $this->cleanupTemporaryHtmlFile();

        if (! file_exists($targetPath)) {
            throw CouldNotTakeBrowsershot::chromeOutputEmpty($targetPath);
        }
    }

    public function evaluate($pageFunction)
    {
        $command = $this->createEvaluateCommand($pageFunction);

        return $this->callBrowser($command);
    }

    public function triggeredRequests()
    {
        $command = $this->createTriggeredRequestsListCommand();

        return json_decode($this->callBrowser($command), true);
    }

    public function applyManipulations($imagePath)
    {
        Image::load($imagePath)
            ->manipulate($this->imageManipulations)
            ->save();
    }

    public function createBodyHtmlCommand()
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;

        return $this->createCommand($url, 'content');
    }

    public function createScreenshotCommand($targetPath = null)
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;

        $options = [
            'type' => $this->screenshotType,
        ];
        if ($targetPath) {
            $options['path'] = $targetPath;
        }

        if ($this->screenshotQuality) {
            $options['quality'] = $this->screenshotQuality;
        }

        $command = $this->createCommand($url, 'screenshot', $options);

        if (! $this->showScreenshotBackground) {
            $command['options']['omitBackground'] = true;
        }

        return $command;
    }

    public function createPdfCommand($targetPath = null)
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;

        $options = [];
        if ($targetPath) {
            $options['path'] = $targetPath;
        }

        $command = $this->createCommand($url, 'pdf', $options);

        if ($this->showBackground) {
            $command['options']['printBackground'] = true;
        }

        return $command;
    }

    public function createEvaluateCommand($pageFunction)
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;

        $options = [
            'pageFunction' => $pageFunction,
        ];

        return $this->createCommand($url, 'evaluate', $options);
    }

    public function createTriggeredRequestsListCommand()
    {
        $url = $this->html ? $this->createTemporaryHtmlFile() : $this->url;

        return $this->createCommand($url, 'requestsList');
    }

    public function setRemoteInstance($ip = '127.0.0.1', $port = 9222)
    {
        // assuring that ip and port does actually contains a value
        if ($ip && $port) {
            $this->setOption('remoteInstanceUrl', 'http://'.$ip.':'.$port);
        }

        return $this;
    }

    public function setWSEndpoint($endpoint)
    {
        if (! is_null($endpoint)) {
            $this->setOption('browserWSEndpoint', $endpoint);
        }

        return $this;
    }

    protected function getOptionArgs()
    {
        $args = $this->chromiumArguments;

        if ($this->noSandbox) {
            $args[] = '--no-sandbox';
        }

        if ($this->proxyServer) {
            $args[] = '--proxy-server='.$this->proxyServer;
        }

        return $args;
    }

    protected function createCommand($url, $action, array $options = [])
    {
        $command = compact('url', 'action', 'options');

        $command['options']['args'] = $this->getOptionArgs();

        if (! empty($this->additionalOptions)) {
            $command['options'] = array_merge_recursive($command['options'], $this->additionalOptions);
        }

        return $command;
    }

    protected function createTemporaryHtmlFile()
    {
        $this->temporaryHtmlDirectory = tempnam(sys_get_temp_dir(), 'tmphtml_');

        $newName = $this->temporaryHtmlDirectory.'.html';
        rename($this->temporaryHtmlDirectory, $newName);
        $this->temporaryHtmlDirectory = $newName;

        file_put_contents($temporaryHtmlFile = $this->temporaryHtmlDirectory, $this->html);

        return "file://{$temporaryHtmlFile}";
    }

    protected function cleanupTemporaryHtmlFile()
    {
        if ($this->temporaryHtmlDirectory) {
            unlink($this->temporaryHtmlDirectory);
        }
    }

    protected function createTemporaryOptionsFile($command)
    {
        $this->temporaryOptionsDirectory = tempnam(sys_get_temp_dir(), 'tmpoption_');

        $newName = $this->temporaryOptionsDirectory.'.js';
        rename($this->temporaryOptionsDirectory, $newName);
        $this->temporaryOptionsDirectory = $newName;

        file_put_contents($temporaryOptionsFile = $this->temporaryOptionsDirectory, $command);

        return "{$temporaryOptionsFile}";
    }

    protected function cleanupTemporaryOptionsFile()
    {
        if ($this->temporaryOptionsDirectory) {
            unlink($this->temporaryOptionsDirectory);
        }
    }

    protected function callBrowser(array $command)
    {
        $fullCommand = $this->getFullCommand($command);

        $process = new Process($fullCommand);
        $process->setTimeout($this->timeout);

        $process->run();

        if ($process->isSuccessful()) {
            return rtrim($process->getOutput());
        }

        $this->cleanupTemporaryOptionsFile();
        $process->clearOutput();

        if ($process->getExitCode() === 2) {
            throw new ElementNotFound($this->additionalOptions['selector']);
        }

        throw new ProcessFailedException($process);
    }

    protected function getFullCommand(array $command)
    {
        $nodeBinary = $this->nodeBinary ?: 'node';

        $binPath = $this->binPath ?: __DIR__.'/../bin/browser.js';

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $fullCommand =
                $nodeBinary.' '
                .escapeshellarg($binPath).' '
                .'"'.str_replace('"', '\"', (json_encode($command))).'"';

            return escapeshellcmd($fullCommand);
        }

        $setIncludePathCommand = "PATH={$this->includePath}";

        $setNodePathCommand = $this->getNodePathCommand($nodeBinary);

        $optionsCommand = $this->getOptionsCommand(json_encode($command));

        return
            $setIncludePathCommand.' '
            .$setNodePathCommand.' '
            .$nodeBinary.' '
            .escapeshellarg($binPath).' '
            .$optionsCommand;
    }

    protected function getNodePathCommand($nodeBinary)
    {
        if ($this->nodeModulePath) {
            return "NODE_PATH='{$this->nodeModulePath}'";
        }
        if ($this->npmBinary) {
            return "NODE_PATH=`{$nodeBinary} {$this->npmBinary} root -g`";
        }

        return 'NODE_PATH=`npm root -g`';
    }

    protected function getOptionsCommand($command)
    {
        if ($this->writeOptionsToFile) {
            $temporaryOptionsFile = $this->createTemporaryOptionsFile($command);

            return escapeshellarg("-f {$temporaryOptionsFile}");
        }

        return escapeshellarg($command);
    }

    protected function arraySet(array &$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}
