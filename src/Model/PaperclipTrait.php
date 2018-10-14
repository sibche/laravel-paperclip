<?php
namespace Czim\Paperclip\Model;

use Czim\FileHandling\Contracts\Storage\StorableFileFactoryInterface;
use Czim\Paperclip\Attachment\Attachment;
use Czim\Paperclip\Contracts\AttachableInterface;
use Czim\Paperclip\Contracts\AttachmentFactoryInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

trait PaperclipTrait
{

    /**
     * All of the model's current attached files.
     *
     * @var AttachmentInterface[]
     */
    protected $attachedFiles = [];

    /**
     * Whether an attachment has been updated and requires further processing.
     *
     * @var bool
     */
    protected $attachedUpdated = false;


    /**
     * Returns the list of attached files.
     *
     * @return AttachmentInterface[]
     */
    public function getAttachedFiles()
    {
        return $this->attachedFiles;
    }

    /**
     * Add a new file attachment type to the list of available attachments.
     *
     * @param string $name
     * @param array  $options
     */
    public function hasAttachedFile($name, array $options = [])
    {
        /** @var Model $this */

        /** @var AttachmentFactoryInterface $factory */
        $factory = app(AttachmentFactoryInterface::class);

        $attachment = $factory->create($this, $name, $options);

        $this->attachedFiles[ $name ] = $attachment;
    }

    /**
     * Registers the observers.
     */
    public static function bootPaperclipTrait()
    {
        static::saved(function ($model) {
            /** @var Model|AttachableInterface $model */
            if ($model->attachedUpdated) {
                // Unmark attachment being updated, so the processing isn't fired twice
                // when the attachedfile performs a model update for the variants column.
                $model->attachedUpdated = false;

                foreach ($model->getAttachedFiles() as $attachedFile) {
                    $attachedFile->afterSave($model);
                }
            }
        });

        static::deleting(function ($model) {
            /** @var Model|AttachableInterface $model */
            foreach ($model->getAttachedFiles() as $attachedFile) {
                $attachedFile->beforeDelete($model);
            }
        });

        static::deleted(function ($model) {
            /** @var Model|AttachableInterface $model */
            foreach ($model->getAttachedFiles() as $attachedFile) {
                $attachedFile->afterDelete($model);
            }
        });
    }

    /**
     * Get all of the current attributes and attached files on the model.
     *
     * @return mixed
     */
    public function getAttributes()
    {
        // Quick, ugly hack to handle 5.6.37 update (getAttributes() now also called in performInsert()).
        if ($this->isPerformInsertInBacktrace()) {
            return parent::getAttributes();
        }

        return array_merge($this->attachedFiles, parent::getAttributes());
    }

    /**
     * Overridden to prevent attempts to persist attachment attributes directly.
     *
     * Reason this is required: Laravel 5.5 changed the getDirty() behavior.
     *
     * {@inheritdoc}
     */
    protected function originalIsEquivalent($key, $current)
    {
        if (array_key_exists($key, $this->attachedFiles)) {
            return true;
        }

        return parent::originalIsEquivalent($key, $current);
    }

    /**
     * Return the image paths for a given attachment.
     *
     * @param string $attachmentName
     * @return string[]
     */
    public function pathsForAttachment($attachmentName)
    {
        $paths = [];

        if (isset($this->attachedFiles[ $attachmentName ])) {
            $attachment = $this->attachedFiles[ $attachmentName ];

            foreach ($attachment->variants(true) as $variant) {
                $paths[ $variant ] = $attachment->variantPath($variant);
            }
        }

        return $paths;
    }

    /**
     * Return the image urls for a given attachment.
     *
     * @param  string $attachmentName
     * @return array
     */
    public function urlsForAttachment($attachmentName)
    {
        $urls = [];

        if (isset($this->attachedFiles[ $attachmentName ])) {
            $attachment = $this->attachedFiles[ $attachmentName ];

            foreach ($attachment->variants(true) as $variant) {
                $urls[ $variant ] = $attachment->url($variant);
            }
        }

        return $urls;
    }

    /**
     * Marks that at least one attachment on the model has been updated and should be processed.
     */
    public function markAttachmentUpdated()
    {
        $this->attachedUpdated = true;
    }

    /**
     * @return bool
     */
    protected function isPerformInsertInBacktrace()
    {
        return false !== Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function (array $step) {
            return  Arr::get($step, 'function') === 'performInsert'
                &&  Arr::get($step, 'class') === Model::class;
        }, false);
    }


    /**
     * Overridden to redirect getting of attachment values.
     *
     * {@inheritdoc}
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->attachedFiles)) {
            return $this->getAttributeForAttachment($key);
        }

        return parent::__get($key);
    }

    /**
     * Overridden to redirect setting of attachment values.
     *
     * {@inheritdoc}
     */
    public function __set($key, $value)
    {
        if (array_key_exists($key, $this->attachedFiles)) {
            $this->setAttributeForAttachment($key, $value);
            return;
        }

        parent::__set($key, $value);
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getAttributeForAttachment($key)
    {
        if ( ! array_key_exists($key, $this->attachedFiles)) {
            throw new InvalidArgumentException("No attachment for '{$key}'");
        }

        return $this->attachedFiles[ $key ];
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    protected function setAttributeForAttachment($key, $value)
    {
        if ($value) {
            $attachedFile = $this->attachedFiles[ $key ];

            if ($value === $this->nullAttachmentIdentifier()) {
                $attachedFile->setToBeDeleted();
                return;
            }

            /** @var StorableFileFactoryInterface $factory */
            $factory = app(StorableFileFactoryInterface::class);

            $attachedFile->setUploadedFile(
                $factory->makeFromAny($value)
            );
        }

        $this->attachedUpdated = true;

        return;
    }

    /**
     * Returns string value that, when set, should clear the attachment.
     */
    protected function nullAttachmentIdentifier()
    {
        return Attachment::NULL_ATTACHMENT;
    }

}
