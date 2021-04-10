<?php
require_once __DIR__ . "/vendor/autoload.php";
define("MAIN_PATH", __DIR__);
$climate = new \League\CLImate\CLImate();
if(Phar::canWrite() && isset($argv[1]) && $argv[1] === "_pack"){
    \miner\Packager::package($climate);
    array_shift($argv);
}
$climate->br()->flank("<bold><underline><cyan>Preparing</cyan></bold></underline>");
if(\miner\ComposerDetector::detect()){
    $climate->out(\miner\Output::indent("Detected composer at <white>" . \miner\ComposerDetector::getCommand() . "</white>"));
    $composerFile = new \miner\JSONProtector("composer.json");
    $composerFile->unprotect();
    $climate->comment(\miner\Output::indent("Prepared composer.json"));
    $climate->br()->flank("<bold><underline><cyan>Executing</cyan></bold></underline>");
    $composer = new \miner\Composer(\miner\ComposerDetector::getCommand(), $climate);
    $composer->execute(array_slice($argv, 1));
    while($composer->getLine());
    $composerFile->protect();
    $climate->br()->flank("<bold><underline><cyan>Porting Infrastructure</cyan></bold></underline>");
    if(is_dir(getcwd() . "/vendor")){
        $iterator = new RecursiveDirectoryIterator("vendor");
        $climate->br();
        $progress = $climate->progress(iterator_count(new RecursiveIteratorIterator($iterator)));
        searchDirectory($iterator, $progress, $climate);
    }
    else{
        $climate->comment(\miner\Output::indent("Nothing to port"));
    }
    $climate->br();
}
else{
    $climate->red()->underline(\miner\Output::indent("Error finding composer"));
}
function searchDirectory(RecursiveDirectoryIterator $iterator, \League\CLImate\TerminalObject\Dynamic\Progress $progress, \League\CLImate\CLImate $climate){
    foreach($iterator as $file){
        $file = explodePath($file);
        try{
            $progress->advance();
        }
        catch(Exception $e){

        }
        if($iterator->hasChildren()){
            searchDirectory($iterator->getChildren(), $progress, $climate);
        }
        else{
            if($file[count($file)-1] === "composer.json"){
                $autoloaders = json_decode(file_get_contents(implode("/", $file)), true)["autoload"];
                $loaderIterator = new RecursiveArrayIterator($autoloaders);
                foreach($loaderIterator as $type => $loaders){
                    if(is_array($loaders)){
                        foreach($loaders as $name => $item) {
                            if($type === "psr-0") $to = "src";
                            else $to = "src/" . str_replace("\\", "/", $name);
                            @mkdir($to, 0775, true);
                            $toCopy =  implode("/", array_slice($file, 0, -1)) . "/" . $item;
                            if(!is_dir($toCopy)){
                                //$climate->comment(\miner\Output::indent($toCopy. " is not a directory.. skipping copy"));
                                continue;
                            }
                            copyDirectory($toCopy, $to, $progress);
                        }
                    }
                }
            }
        }
        usleep(2000);
    }
}
function copyDirectory($from, $to, \League\CLImate\TerminalObject\Dynamic\Progress $progress){
    foreach (
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST) as $item
    ) {
        //$progress->advance();
        if ($item->isDir()) {
            @mkdir($to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        } else {
            copy($item, $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        }
    }
}
function explodePath($path){
    return explode('/', $path);
}
