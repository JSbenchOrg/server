<?php
namespace JSB\Models;

class Entry
{
    /** @var  int */
    public $id;

    /** @var  string */
    public $title;

    /** @var  string */
    public $code;

    /** @var Metric[] */
    public $totals;

    protected $obsoleteFlag = false;

    public function __construct($id, $title, $code, $totals = [])
    {
        $this->id = $id;
        $this->title = $title;
        $this->code = $code;
        $this->totals = $totals;
    }

    public function inheritTotals($totals)
    {
        // pivot
        $indexList = [];
        foreach ($this->totals as $index => $currentItem) {
            $key = $currentItem->getChecksum();
            $indexList[$key] = $index;
        }

        foreach ($totals as $item) {
            $checksum = $item->getChecksum();

            if (array_key_exists($checksum, $indexList)) {
                // updating existing total
                $existingIndex = $indexList[$checksum];
                $this->totals[$existingIndex]->incrementValues($item);

            } else {
                // insert
                $index++;
                $this->totals[$index] = $item;
                $indexList[$key] = $index;
            }
        }
    }

    public function isObsolete()
    {
        return $this->obsoleteFlag;
    }

    public function setObsolete()
    {
        $this->obsoleteFlag = true;
    }
}
