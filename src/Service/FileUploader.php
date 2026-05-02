<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileUploader
{
    private $targetDirectory;
    private $slugger;

     public function __construct($targetDirectory, SluggerInterface $slugger)
     {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
     }

    public function upload(UploadedFile $file)
    {        
        $og_Filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe_Filename = $this->slugger->slug($og_Filename);
        $final_fileName = $safe_Filename.'-'.uniqid().'.'.$file->guessExtension();
        try {
            $file->move($this->getTargetDirectory(), $final_fileName);
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
            // redirect('upload_error');
        }
        return $final_fileName;
    }

    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }
}