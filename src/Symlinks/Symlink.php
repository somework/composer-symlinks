<?php

namespace SomeWork\Symlinks;

class Symlink
{
    /**
     * @var string
     */
    protected $target = '';

    /**
     * @var string
     */
    protected $link = '';

    /**
     * @var bool
     */
    protected $absolutePath = false;

    /**
     * @var bool
     */
    protected $forceCreate = false;

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param string $target
     *
     * @return Symlink
     */
    public function setTarget(string $target): Symlink
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     *
     * @return Symlink
     */
    public function setLink(string $link): Symlink
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAbsolutePath(): bool
    {
        return $this->absolutePath;
    }

    /**
     * @param bool $absolutePath
     *
     * @return Symlink
     */
    public function setAbsolutePath(bool $absolutePath): Symlink
    {
        $this->absolutePath = $absolutePath;
        return $this;
    }

    /**
     * @return bool
     */
    public function isForceCreate(): bool
    {
        return $this->forceCreate;
    }

    /**
     * @param bool $forceCreate
     *
     * @return Symlink
     */
    public function setForceCreate(bool $forceCreate): Symlink
    {
        $this->forceCreate = $forceCreate;
        return $this;
    }
}
