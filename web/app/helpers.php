<?php

/**
 * Định dạng kích thước file thành định dạng dễ đọc (KB, MB, GB)
 * 
 * @param int $bytes Kích thước file tính bằng bytes
 * @param int $decimals Số chữ số thập phân
 * @return string Kích thước file đã định dạng
 */
function human_filesize($bytes, $decimals = 2)
{
    $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
} 