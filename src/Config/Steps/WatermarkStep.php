<?php
/**
 * bahadorbabazadeh
 * 6/21/20 19:22
 */

namespace Czim\Paperclip\Config\Steps;

class WatermarkStep extends VariantStep
{

    /**
     * @var string
     */
    protected $defaultName = 'watermark';

    protected $path;

    protected $position;

    protected $opacity;

    public function path($path)
    {
        $this->path = $path;

        return $this;
    }

    public function position($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @param mixed $opacity
     */
    public function opacity(float $opacity)
    {
        $this->opacity = $opacity;
        return $this;
    }


    /**
     * @return array
     */
    protected function getStepOptionArray()
    {
        return [
            'watermark' => $this->path,
            'position' => $this->position,
            'opacity' => $this->opacity,
        ];
    }
}
