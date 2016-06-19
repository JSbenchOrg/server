<?php
namespace JSB\Models;

class Harness
{
    /** @var string */
    public $html;

    /** @var string */
    public $setUp;

    /** @var string */
    public $tearDown;

    public function __construct($html, $setUp, $tearDown)
    {
        $this->html = $html;
        $this->setUp = $setUp;
        $this->tearDown = $tearDown;
    }
}
