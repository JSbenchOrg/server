<?php
namespace JSB\Models;

class Metric
{
    public $id;

    /** @var  string opsPerSec */
    public $metricType;

    /** @var  string|int */
    public $metricValue;

    /** @var  int */
    public $runCount;

    /** @var  string */
    public $browserName;

    /** @var  string */
    public $browserVersion;

    /** @var  string */
    public $osArchitecture;

    /** @var  string */
    public $osFamily;

    /** @var  string */
    public $osVersion;

    public function __construct(
        $id,
        $metricType,
        $metricValue,
        $runCount,
        $browserName,
        $browserVersion,
        $osArchitecture,
        $osFamily,
        $osVersion
    ) {
        $this->id = $id;
        $this->metricType = $metricType;
        $this->metricValue = $metricValue;
        $this->runCount = $runCount;
        $this->browserName = $browserName;
        $this->browserVersion = $browserVersion;
        $this->osArchitecture = $osArchitecture;
        $this->osFamily = $osFamily;
        $this->osVersion = $osVersion;
    }

    public function getChecksum()
    {
        return md5(implode('|', [
            $this->browserName,
            $this->browserVersion,
            $this->osArchitecture,
            $this->osFamily,
            $this->osVersion,
        ]));
    }

    public function incrementValues(Metric $input)
    {
        $newTotalValue = ($this->metricValue * $this->runCount + $input->metricValue * $input->runCount);
        $this->runCount += $input->runCount;
        $this->metricValue = $newTotalValue / $this->runCount;
    }
}
