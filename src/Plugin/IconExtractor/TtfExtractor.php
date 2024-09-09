<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Utility to parse TTF font files.
 *
 * Version: 1.0
 * Date:    2011-06-18
 * Author:  Olivier PLATHEY.
 */
#[IconExtractor(
  id: 'ttf',
  label: new TranslatableMarkup('Truetype'),
  description: new TranslatableMarkup('..'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class TtfExtractor extends IconExtractorBase {
  public $f;
  public $tables;
  public $unitsPerEm;
  public $xMin, $yMin, $xMax, $yMax;
  public $numberOfHMetrics;
  public $numGlyphs;
  public $widths;
  public $chars;
  public $postScriptName;
  public $Embeddable;
  public $Bold;
  public $typoAscender;
  public $typoDescender;
  public $capHeight;
  public $italicAngle;
  public $underlinePosition;
  public $underlineThickness;
  public $isFixedPitch;

  /**
   *
   */
  public function Parse($file) {
    $this->f = fopen($file, 'rb');
    if (!$this->f) {
      $this->Error('Can\'t open file: ' . $file);
    }

    $version = $this->Read(4);
    if ($version == 'OTTO') {
      $this->Error('OpenType fonts based on PostScript outlines are not supported');
    }
    if ($version != "\x00\x01\x00\x00") {
      $this->Error('Unrecognized file format');
    }
    $numTables = $this->ReadUShort();
    // searchRange, entrySelector, rangeShift.
    $this->Skip(3 * 2);
    $this->tables = [];
    for ($i = 0; $i < $numTables; $i++) {
      $tag = $this->Read(4);
      // checkSum.
      $this->Skip(4);
      $offset = $this->ReadULong();
      // Length.
      $this->Skip(4);
      $this->tables[$tag] = $offset;
    }

    $this->ParseHead();
    $this->ParseHhea();
    $this->ParseMaxp();
    $this->ParseHmtx();
    $this->ParseCmap();
    $this->ParseName();
    $this->ParseOS2();
    $this->ParsePost();

    fclose($this->f);
  }

  /**
   *
   */
  public function ParseHead() {
    $this->Seek('head');
    // version, fontRevision, checkSumAdjustment.
    $this->Skip(3 * 4);
    $magicNumber = $this->ReadULong();
    if ($magicNumber != 0x5F0F3CF5) {
      $this->Error('Incorrect magic number');
    }
    // Flags.
    $this->Skip(2);
    $this->unitsPerEm = $this->ReadUShort();
    // created, modified.
    $this->Skip(2 * 8);
    $this->xMin = $this->ReadShort();
    $this->yMin = $this->ReadShort();
    $this->xMax = $this->ReadShort();
    $this->yMax = $this->ReadShort();
  }

  /**
   *
   */
  public function ParseHhea() {
    $this->Seek('hhea');
    $this->Skip(4 + 15 * 2);
    $this->numberOfHMetrics = $this->ReadUShort();
  }

  /**
   *
   */
  public function ParseMaxp() {
    $this->Seek('maxp');
    $this->Skip(4);
    $this->numGlyphs = $this->ReadUShort();
  }

  /**
   *
   */
  public function ParseHmtx() {
    $this->Seek('hmtx');
    $this->widths = [];
    for ($i = 0; $i < $this->numberOfHMetrics; $i++) {
      $advanceWidth = $this->ReadUShort();
      // Lsb.
      $this->Skip(2);
      $this->widths[$i] = $advanceWidth;
    }
    if ($this->numberOfHMetrics < $this->numGlyphs) {
      $lastWidth = $this->widths[$this->numberOfHMetrics - 1];
      $this->widths = array_pad($this->widths, $this->numGlyphs, $lastWidth);
    }
  }

  /**
   *
   */
  public function ParseCmap() {
    $this->Seek('cmap');
    // Version.
    $this->Skip(2);
    $numTables = $this->ReadUShort();
    $offset31 = 0;
    for ($i = 0; $i < $numTables; $i++) {
      $platformID = $this->ReadUShort();
      $encodingID = $this->ReadUShort();
      $offset = $this->ReadULong();
      if ($platformID == 3 && $encodingID == 1) {
        $offset31 = $offset;
      }
    }
    if ($offset31 == 0) {
      $this->Error('No Unicode encoding found');
    }

    $startCount = [];
    $endCount = [];
    $idDelta = [];
    $idRangeOffset = [];
    $this->chars = [];
    fseek($this->f, $this->tables['cmap'] + $offset31, SEEK_SET);
    $format = $this->ReadUShort();
    if ($format != 4) {
      $this->Error('Unexpected subtable format: ' . $format);
    }
    // length, language.
    $this->Skip(2 * 2);
    $segCount = $this->ReadUShort() / 2;
    // searchRange, entrySelector, rangeShift.
    $this->Skip(3 * 2);
    for ($i = 0; $i < $segCount; $i++) {
      $endCount[$i] = $this->ReadUShort();
    }
    // reservedPad.
    $this->Skip(2);
    for ($i = 0; $i < $segCount; $i++) {
      $startCount[$i] = $this->ReadUShort();
    }
    for ($i = 0; $i < $segCount; $i++) {
      $idDelta[$i] = $this->ReadShort();
    }
    $offset = ftell($this->f);
    for ($i = 0; $i < $segCount; $i++) {
      $idRangeOffset[$i] = $this->ReadUShort();
    }

    for ($i = 0; $i < $segCount; $i++) {
      $c1 = $startCount[$i];
      $c2 = $endCount[$i];
      $d = $idDelta[$i];
      $ro = $idRangeOffset[$i];
      if ($ro > 0) {
        fseek($this->f, $offset + 2 * $i + $ro, SEEK_SET);
      }
      for ($c = $c1; $c <= $c2; $c++) {
        if ($c == 0xFFFF) {
          break;
        }
        if ($ro > 0) {
          $gid = $this->ReadUShort();
          if ($gid > 0) {
            $gid += $d;
          }
        }
        else {
          $gid = $c + $d;
        }
        if ($gid >= 65536) {
          $gid -= 65536;
        }
        if ($gid > 0) {
          $this->chars[$c] = $gid;
        }
      }
    }
  }

  /**
   *
   */
  public function ParseName() {
    $this->Seek('name');
    $tableOffset = ftell($this->f);
    $this->postScriptName = '';
    // Format.
    $this->Skip(2);
    $count = $this->ReadUShort();
    $stringOffset = $this->ReadUShort();
    for ($i = 0; $i < $count; $i++) {
      // platformID, encodingID, languageID.
      $this->Skip(3 * 2);
      $nameID = $this->ReadUShort();
      $length = $this->ReadUShort();
      $offset = $this->ReadUShort();
      if ($nameID == 6) {
        // PostScript name.
        fseek($this->f, $tableOffset + $stringOffset + $offset, SEEK_SET);
        $s = $this->Read($length);
        $s = str_replace(chr(0), '', $s);
        $s = preg_replace('|[ \[\](){}<>/%]|', '', $s);
        $this->postScriptName = $s;
        break;
      }
    }
    if ($this->postScriptName == '') {
      $this->Error('PostScript name not found');
    }
  }

  /**
   *
   */
  public function ParseOS2() {
    $this->Seek('OS/2');
    $version = $this->ReadUShort();
    // xAvgCharWidth, usWeightClass, usWidthClass.
    $this->Skip(3 * 2);
    $fsType = $this->ReadUShort();
    $this->Embeddable = ($fsType != 2) && ($fsType & 0x200) == 0;
    $this->Skip(11 * 2 + 10 + 4 * 4 + 4);
    $fsSelection = $this->ReadUShort();
    $this->Bold = ($fsSelection & 32) != 0;
    // usFirstCharIndex, usLastCharIndex.
    $this->Skip(2 * 2);
    $this->typoAscender = $this->ReadShort();
    $this->typoDescender = $this->ReadShort();
    if ($version >= 2) {
      $this->Skip(3 * 2 + 2 * 4 + 2);
      $this->capHeight = $this->ReadShort();
    }
    else {
      $this->capHeight = 0;
    }
  }

  /**
   *
   */
  public function ParsePost() {
    $this->Seek('post');
    // Version.
    $this->Skip(4);
    $this->italicAngle = $this->ReadShort();
    // Skip decimal part.
    $this->Skip(2);
    $this->underlinePosition = $this->ReadShort();
    $this->underlineThickness = $this->ReadShort();
    $this->isFixedPitch = ($this->ReadULong() != 0);
  }

  /**
   *
   */
  public function Error($msg) {
    if (PHP_SAPI == 'cli') {
      die("Error: $msg\n");
    }
    else {
      die("<b>Error</b>: $msg");
    }
  }

  /**
   *
   */
  public function Seek($tag) {
    if (!isset($this->tables[$tag])) {
      $this->Error('Table not found: ' . $tag);
    }
    fseek($this->f, $this->tables[$tag], SEEK_SET);
  }

  /**
   *
   */
  public function Skip($n) {
    fseek($this->f, $n, SEEK_CUR);
  }

  /**
   *
   */
  public function Read($n) {
    return fread($this->f, $n);
  }

  /**
   *
   */
  public function ReadUShort() {
    $a = unpack('nn', fread($this->f, 2));
    return $a['n'];
  }

  /**
   *
   */
  public function ReadShort() {
    $a = unpack('nn', fread($this->f, 2));
    $v = $a['n'];
    if ($v >= 0x8000) {
      $v -= 65536;
    }
    return $v;
  }

  /**
   *
   */
  public function ReadULong() {
    $a = unpack('NN', fread($this->f, 4));
    return $a['N'];
  }

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $path = "/path/to/font.ttf";
    $this->Parse($path);
    $glyphs = $this->chars;
    // $chars is an associative array with:
    // - key: position index, non-consecutive
    // - value: glyph ID, consecutive
    // Examples:
    // 32 => 3
    // 168 => 5
    // 169 => 6
    // 174 => 7
    // Getting the Glyph Name from a TTF or OTF font file
    // In TrueType-based fonts (.TTF files), you can try parsing the 'post'
    // table.
    if (is_null($this->post) || is_empty($this->post)) {
      // But, only format 2.0 explicitly stores glyph names. If the post table
      // is format 3.0, there are no glyph names stored.
      return [];
    }
    ksm($this->post);
    ksm("ttf", $glyphs);
    return [];
  }

}
