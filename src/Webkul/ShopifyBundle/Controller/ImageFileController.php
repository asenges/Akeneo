<?php

namespace Webkul\ShopifyBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Navneet Kumar <navneetkumar.symfony813@webkul.com>
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
$obj = new \Webkul\ShopifyBundle\Listener\LoadingClassListener();
$obj->checkVersionAndCreateClassAliases();
class ImageFileController
{

     /**
     * @param FilesystemProvider            $filesystemProvider
     * @param FileInfoRepositoryInterface   $fileInfoRepository
     * @param array                         $filesystemAliases
     */
    public function __construct(
        \FilesystemProvider $filesystemProvider,
        \FileInfoRepositoryInterface $fileInfoRepository,
        array $filesystemAliases
    ) {
        $this->filesystemProvider = $filesystemProvider;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->filesystemAliases = $filesystemAliases;
    }

    /**
     * @param string $fileName
     *
     * @throws NotFoundHttpException
     *
     * @return StreamedFileResponse
     */
    public function downloadAction($filename)
    {
        $filename = urldecode($filename);
        foreach ($this->filesystemAliases as $alias) {
            $fs = $this->filesystemProvider->getFilesystem($alias);
            if ($fs->has($filename)) {
                $stream = $fs->readStream($filename);
                $headers = [];

                if (null !== $fileInfo = $this->fileInfoRepository->findOneByIdentifier($filename)) {
                    $headers['Content-Type'] = sprintf('%s', $fileInfo->getMimeType());
                }

                return new \StreamedFileResponse($stream, 200, $headers);
            }
        }

        throw new NotFoundHttpException(
            sprintf('File with key "%s" could not be found.', $filename)
        );
    }
}
