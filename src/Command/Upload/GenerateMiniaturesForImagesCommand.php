<?php

namespace App\Command\Upload;

use App\Controller\Core\Application;
use App\Controller\Core\Env;
use App\Controller\Validators\FileValidator;
use App\Services\Files\DirectoriesHandler;
use App\Services\Files\FilesHandler;
use App\Services\Files\ImageHandler;
use DirectoryIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Avoid implementing:
 * - logic that might remove miniatures or normal files, as this might lead to removal of private data
 *
 * Class GenerateMiniaturesForImagesCommand
 * @package App\Command
 */
class GenerateMiniaturesForImagesCommand extends Command
{
    protected static $defaultName = 'pms:upload:generate-miniatures-for-images';

    /**
     * @var Application $app
     */
    private $app;

    /**
     * @var SymfonyStyle $io
     */
    private $io;

    /**
     * @var FilesHandler $files_handler
     */
    private $files_handler;

    /**
     * @var ImageHandler $image_handler
     */
    private $image_handler;

    public function __construct(Application $app, FilesHandler $files_handler, ImageHandler $image_handler, string $name = null) {
        parent::__construct($name);
        $this->files_handler = $files_handler;
        $this->image_handler = $image_handler;
        $this->app           = $app;
    }

    protected function configure(): void
    {
        $this
            ->setDescription("
                This command will:
                 - generate miniatures for uploaded images if some are missing,
                 
                 Keep in mind:
                 - if file size is small enough, it's miniature will not be generated
            ");
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed_upload_directories = [
            Env::getImagesUploadDir(),
        ];

        $this->io->success("Started");
        {
            try{
                foreach($processed_upload_directories as $upload_directory){

                    $absolute_path             = getcwd() . DIRECTORY_SEPARATOR . Env::getPublicRootDir() . DIRECTORY_SEPARATOR . $upload_directory;
                    $absoulte_folders_list     = DirectoriesHandler::buildFoldersTreeForDirectory( new DirectoryIterator($absolute_path), false, true);
                    $files_list_in_directories = $this->files_handler->listAllFilesInDirectories($absoulte_folders_list);

                    foreach($files_list_in_directories as $directory => $files){
                        foreach($files as $filename){

                            $file_absolute_path = $directory . DIRECTORY_SEPARATOR . $filename;
                            $file_object        = new File($file_absolute_path);

                            preg_match('#\/upload\/(.*)#', $directory, $matches);

                            $upload_directory_structure = $matches[1];

                            if( !array_key_exists(1, $matches) ){
                                $this->io->error("There is something wrong with this file, it's not in the upload directory? ");
                            }

                            $target_miniature_file_absolute_path = getcwd() .
                                DIRECTORY_SEPARATOR .
                                Env::getPublicRootDir() .
                                DIRECTORY_SEPARATOR .
                                Env::getMiniaturesUploadDir() .
                                DIRECTORY_SEPARATOR .
                                $upload_directory_structure .
                                DIRECTORY_SEPARATOR .
                                $filename;

                            if( file_exists($target_miniature_file_absolute_path) ){
                                continue;
                            }

                            $this->io->note("Creating miniature for file");
                            $this->io->listing([
                               "from: " . $file_absolute_path,
                               "to : "  . $target_miniature_file_absolute_path,
                            ]);

                            if( !FileValidator::isFileImage($file_object) ){
                                $this->io->warning("File is not an image");
                                return 1;
                            }

                            if( !FileValidator::isImageResizable($file_object) ){
                                $this->io->warning("Image type is not resizable");
                                return 1;
                            }

                            $this->image_handler->createMiniature($file_absolute_path, true, $target_miniature_file_absolute_path);
                            $this->io->listing([
                                "status: " . $this->image_handler->getLastStatus(),
                            ]);
                        }
                    }
                }

            }catch(\Exception $e){
                $this->app->logger->critical("There was an error.");
                $this->app->logger->critical($e->getMessage());
                $this->app->logger->critical($e->getTraceAsString());
            }

        }
        $this->io->success("Finished");

        return 0;
    }

}
