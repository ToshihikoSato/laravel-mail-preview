<?php

namespace Themsaid\MailPreview;

use Illuminate\Support\Facades\Session;
use Swift_Mime_Message;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Transport\Transport;

class PreviewTransport extends Transport
{
    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Get the preview path.
     *
     * @var string
     */
    protected $previewPath;

    /**
     * Time in seconds to keep old previews.
     *
     * @var int
     */
    private $lifeTime;

    /**
     * Create a new preview transport instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  string $previewPath
     * @param  int $lifeTime
     *
     * @return void
     */
    public function __construct(Filesystem $files, $previewPath, $lifeTime = 60)
    {
        $this->files = $files;
        $this->previewPath = $previewPath;
        $this->lifeTime = $lifeTime;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        /*
         * 2016/07/26 ToshihikoSato
         * Next 2 sentences extracts previewPath from subject field which is set by caller.
         */
        $subject = $message->getSubject();
        $this->previewPath = dirname($subject);

        $this->createEmailPreviewDirectory();

        $this->cleanOldPreviews();

        Session::put('mail_preview_path', basename($previewPath = $this->getPreviewFilePath($message)));

        /*
         * 2016/06/10 ToshihikoSato
         * Next two sentences are disabled to supress creating ".html" file and ".eml" file.
         */
/*
        $this->files->put(
            $previewPath.'.html',
            $this->getHTMLPreviewContent($message)
        );

        $this->files->put(
            $previewPath.'.eml',
            $this->getEMLPreviewContent($message)
        );
*/
        /*
         * 2016/06/10 ToshihikoSato
         * Next sentence creates a text file $previewPath.txt which has only body of message.
         * getBody() is a method of Swift_Mime_SimpleMimeEntity class which is ancestor of $messae.
         * 
         * 2016/08/11 ToshihikoSato
         * Even if there are mixed EOL characters in the body, all the EOL character will be converted to CR+LF.
         * cf. http://qiita.com/suin/items/cbbbe8844fd734fa20e2
         */
        $crlfMailBody = preg_replace('/\r\n|\r|\n/', '\r\n', $message->getBody());
        $this->files->put(
            $previewPath.'.txt',
            $crlfMailBody
        );
    }

    /**
     * Get the path to the email preview file.
     *
     * @param  \Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getPreviewFilePath(Swift_Mime_Message $message)
    {
        /*
         * 2016/07/25 ToshihikoSato
         * Next threeo sentences are disabled to change the filename creation logic.
         */
/*
        $to = str_replace(['@', '.'], ['_at_', '_'], array_keys($message->getTo())[0]);

        $subject = $message->getSubject();

        return $this->previewPath.'/'.str_slug($message->getDate().'_'.$to.'_'.$subject, '_');
*/
        /*
         * 2016/07/26 ToshihikoSato
         * Next sentence creates the filename of a preview mail textfile.
         */
        $subject = $message->getSubject();
        return $this->previewPath.'/'.basename($subject);
    }

    /**
     * Get the HTML content for the preview file.
     *
     * @param  \Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getHTMLPreviewContent(Swift_Mime_Message $message)
    {
        $messageInfo = $this->getMessageInfo($message);

        return $messageInfo.$message->getBody();
    }

    /**
     * Get the EML content for the preview file.
     *
     * @param  \Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getEMLPreviewContent(Swift_Mime_Message $message)
    {
        return $message->toString();
    }

    /**
     * Generate a human readable HTML comment with message info.
     *
     * @param \Swift_Mime_Message $message
     *
     * @return string
     */
    private function getMessageInfo(Swift_Mime_Message $message)
    {
        return sprintf(
            "<!--\nFrom:%s, \nto:%s, \nreply-to:%s, \ncc:%s, \nbcc:%s, \nsubject:%s\n-->\n",
            json_encode($message->getFrom()),
            json_encode($message->getTo()),
            json_encode($message->getReplyTo()),
            json_encode($message->getCc()),
            json_encode($message->getBcc()),
            $message->getSubject()
        );
    }

    /**
     * Create the preview directory if necessary.
     *
     * @return void
     */
    protected function createEmailPreviewDirectory()
    {
        if (! $this->files->exists($this->previewPath)) {
            $this->files->makeDirectory($this->previewPath);
            /*
            * 2016/07/25 ToshihikoSato
            * supress to create .gitignore file.
            */
            // $this->files->put($this->previewPath.'/.gitignore', "*\n!.gitignore");
        }
    }

    /**
     * Delete previews older than the given life time configuration.
     *
     * @return void
     */
    private function cleanOldPreviews()
    {
        $oldPreviews = array_filter($this->files->files($this->previewPath), function ($file) {
            return time() - $this->files->lastModified($file) > $this->lifeTime;
        });

        if ($oldPreviews) {
            $this->files->delete($oldPreviews);
        }
    }
}
