<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Testler için en küçük geçerli .docx — sadece DocxImporter'ın okuduğu parçalar.
 * Stil kimlikleri Türkçe Word'deki gibi ("Balk1") verilebiliyor; importer'ın stil adına
 * ("heading 1") baktığını doğrulamak için.
 */
class FakeDocx
{
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    /**
     * @param  array<string, string>  $footnotes  id → metin
     * @param  array<string, string>  $links  rel id → URL
     */
    public static function make(string $bodyXml, array $footnotes = [], array $links = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document '.self::NS.'><w:body>'.$bodyXml.'</w:body></w:document>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><w:styles '.self::NS.'>'
            .'<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Balk1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Balk2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Alnt"><w:name w:val="Quote"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="BolumBasligi"><w:name w:val="Bölüm Başlığı"/><w:basedOn w:val="Balk1"/></w:style>'
            .'</w:styles>');
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0" encoding="UTF-8"?><w:numbering '.self::NS.'>'
            .'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>'
            .'</w:numbering>');

        if ($footnotes) {
            $zip->addFromString('word/footnotes.xml', '<?xml version="1.0" encoding="UTF-8"?><w:footnotes '.self::NS.'>'
                .collect($footnotes)->map(fn ($text, $id) => '<w:footnote w:id="'.$id.'"><w:p><w:r><w:footnoteRef/></w:r><w:r><w:t xml:space="preserve"> '.htmlspecialchars($text).'</w:t></w:r></w:p></w:footnote>')->implode('')
                .'</w:footnotes>');
        }

        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .collect($links)->map(fn ($url, $id) => '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.htmlspecialchars($url).'" TargetMode="External"/>')->implode('')
            .'</Relationships>');
        $zip->close();

        return $path;
    }

    public static function upload(string $bodyXml, array $footnotes = [], string $name = 'metin.docx'): UploadedFile
    {
        return new UploadedFile(self::make($bodyXml, $footnotes), $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    /** <w:p> kısayolu: $style verilirse paragraf stili, $runs ham w:r içeriği ya da düz metin. */
    public static function p(string $text, ?string $style = null, string $runProps = ''): string
    {
        $pPr = $style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';
        $rPr = $runProps ? '<w:rPr>'.$runProps.'</w:rPr>' : '';

        return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }
}
