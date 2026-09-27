<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Testler için en küçük geçerli EPUB 3: container.xml → OEBPS/content.opf → spine'daki XHTML
 * dosyaları. Kapak ve içindekiler (nav) sayfası da ekleniyor; importer'ın bunları atladığını
 * doğrulamak için.
 */
class FakeEpub
{
    /**
     * @param  array<string, string>  $chapters  dosya adı (OEBPS/Text/ altında) → <body> içeriği
     * @param  array<string, string>  $files  ek dosyalar (OEBPS/ altında yol → içerik), ör. görseller
     */
    public static function make(array $chapters, array $files = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub').'.epub';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');

        $all = ['kapak.xhtml' => '<img src="../Images/kapak.png" alt="Kapak"/>'] + $chapters;
        $manifest = '<item id="nav" href="Text/nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>';
        $spine = '<itemref idref="nav"/>';
        $index = 0;
        foreach ($all as $name => $body) {
            $id = 'c'.$index++;
            $manifest .= '<item id="'.$id.'" href="Text/'.$name.'" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="'.$id.'"/>';
            $zip->addFromString('OEBPS/Text/'.$name, self::xhtml($body));
        }
        $zip->addFromString('OEBPS/Text/nav.xhtml', self::xhtml('<nav epub:type="toc"><h1>İçindekiler</h1><ol><li><a href="bolum1.xhtml">Bölüm</a></li></ol></nav>'));
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="UTF-8"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0"><metadata/><manifest>'.$manifest.'</manifest><spine>'.$spine.'</spine></package>');

        foreach ($files as $name => $contents) {
            $zip->addFromString('OEBPS/'.$name, $contents);
        }
        $zip->close();

        return $path;
    }

    public static function upload(array $chapters, array $files = [], string $name = 'kitap.epub'): UploadedFile
    {
        return new UploadedFile(self::make($chapters, $files), $name, 'application/epub+zip', null, true);
    }

    private static function xhtml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><title>x</title></head><body>'.$body.'</body></html>';
    }
}
