<?php

if (!function_exists('ends_with') && class_exists('\Illuminate\Support\Str')) {
    function ends_with($haystack, $needle)
    {
        return \Illuminate\Support\Str::endsWith($haystack, $needle);
    }
}
