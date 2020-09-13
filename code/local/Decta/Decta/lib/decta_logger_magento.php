<?php

Class DectaLoggerMagento {
    public function __construct($enabled = true) {
        $this->enabled = $enabled;
    }

    public function log($message) {
        if ($this->enabled) {
            Mage::log($message);
        }
    }
}