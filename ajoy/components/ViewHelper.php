<?php

class ViewHelper extends AjoyComponent
{

    /**
     *
     */
    public function encode($value)
    {
        return htmlspecialchars($value);
    }

    /**
     *
     */
    public function linebreaksbr($value)
    {
        return preg_replace('/(\r?\n)/', '<br>$1', $value);
    }

    /**
     *
     */
    public function datetime($value, $format = 'Y-m-d h:i:s')
    {
        return date($format, strtotime($value));
    }

}
