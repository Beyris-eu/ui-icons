<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Plugin\IconExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_icons\Attribute\IconExtractor;
use Drupal\ui_icons\Plugin\IconExtractorBase;
use Drupal\ui_icons\PluginForm\IconPackExtractorForm;

/**
 * Plugin implementation of the ui_icons_extractor.
 */
#[IconExtractor(
  id: 'woff1',
  label: new TranslatableMarkup('WOFF (Web Open Font Format)'),
  description: new TranslatableMarkup('A web font format developed by Mozilla.'),
  forms: [
    'settings' => IconPackExtractorForm::class,
  ]
)]
class Woff1Extractor extends IconExtractorBase {

  /**
   * @const array
   *   Entries name & format from a WOFF header.
   */
  const WOFF_HEADER = [
  // 0x774F4646 'wOFF'
    'signature' => "N",
  // The "sfnt version" of the original file: 0x00010000 for TrueType flavored
  // fonts or 0x4F54544F 'OTTO' for CFF flavored fonts.
    'flavor' => "N",
  // Total size of the WOFF file.
    'length' => "N",
  // Number of entries in directory of font tables.
    'numTables' => "n",
  // Reserved, must be set to zero.
    'reserved' => "n",
  // Total size needed for the uncompressed font data, including the sfnt
  // header, directory, and tables.
    'totalSfntSize' => "N",
  // Major version of the WOFF font, not necessarily the major version of the
  // original sfnt font.
    'majorVersion' => "n",
  // Minor version of the WOFF font, not necessarily the minor version of the
  // original sfnt font.
    'minorVersion' => "n",
  // Offset to metadata block, from beginning of WOFF file; zero if no metadata
  // block is present.
    'metaOffset' => "N",
  // Length of compressed metadata block; zero if no metadata block is present.
    'metaLength' => "N",
  // Uncompressed size of metadata block; zero if no metadata block is present.
    'metaOrigLength' => "N",
  // Offset to protected data block, from beginning of WOFF file; zero if no
  // protected data block is present.
    'privOffset' => "N",
  // Length of protected data block; zero if no protected data block is present.
    'privLength' => "N",
  ];
  const WOFF_HEADERSIZE = 44;

  /**
   * @const   array
   *   Entries name & format from a WOFF table directory.
   */
  const WOFF_TABLEDIRENTRY = [
  // 4-byte sfnt table identifier.
    'tag' => "N",
  // Offset to the data, from beginning of WOFF file.
    'offset' => "N",
  // Length of the compressed data, excluding padding.
    'compLength' => "N",
  // Length of the uncompressed table, excluding padding.
    'origLength' => "N",
  // Checksum of the uncompressed table.
    'origChecksum' => "N",
  ];
  const WOFF_TABLEDIRSIZE = 20;

  /**
   * Read the header from a WOFF file.
   *
   * @param resource $fh
   *   Input file handle.
   *
   * @return array
   *   List of header entries
   */
  protected static function woffReadHeader($fh): array {
    $format = [];
    foreach (self::WOFF_HEADER as $name => $code) {
      $format[] = $code . $name;
    }
    $header = \unpack(\implode('/', $format), \fread($fh, self::WOFF_HEADERSIZE));
    if ($header['signature'] !== 0x774F4646) {
      self::error(__METHOD__, "Bad signature: input file is not a valid WOFF font");
    }
    if ($header['reserved'] !== 0) {
      self::error(__METHOD__, "Invalid header: reserved field should be 0");
    }
    switch ($header['flavor']) {
      case 0x00010000:
        $header['type'] = 'ttf';
        break;

      case 0x4F54544F:
        $header['type'] = 'cff';
        break;

      default:
        $header['type'] = 'xxx';
        break;
    }
    return $header;
  }

  /**
   * Read the table directory from a WOFF file.
   *
   * @param resource $fh
   *   Input file handle.
   * @param int $numTables
   *   Number of font tables to read.
   *
   * @return array
   *   List of table directory entries
   */
  protected static function woffReadTableDir($fh, int $numTables): array {
    $format = [];
    foreach (self::WOFF_TABLEDIRENTRY as $name => $code) {
      $format[] = $code . $name;
    }
    $entries = [];
    for ($n = 0; $n < $numTables; $n++) {
      $entries[] = \unpack(\implode('/', $format), \fread($fh, self::WOFF_TABLEDIRSIZE));
    }
    return $entries;
  }

  /**
   * Read the font tables from a WOFF file.
   *
   * @param resource $fh
   *   Input file handle.
   * @param array $entries
   *   List of table directory entries.
   *
   * @return array
   *   List of font data tables
   */
  protected static function woffReadFontTables($fh, array &$entries): array {
    $tables = [];
    foreach ($entries as $n => &$entry) {
      $origPos = \ftell($fh);
      // Or error.
      \fseek($fh, $entry['offset']);
      if ($entry['compLength'] === $entry['origLength']) {
        $tables[$n] = \fread($fh, $entry['compLength']);
      }
      else {
        $tables[$n] = \gzuncompress(\fread($fh, $entry['compLength']));
      }
      \fseek($fh, $origPos);
      $entry['dataLength'] = \strlen($tables[$n]);
    }
    return $tables;
  }

  /**
   * Read the private data from a WOFF file.
   *
   * @param resource $fh
   *   Input file handle.
   * @param int $offset
   *   Start position.
   * @param int $length
   *   Data length to read.
   *
   * @return string
   *   Private data content
   */
  protected static function woffReadPrivData($fh, int $offset, int $length): string {
    if (!$length) {
      return '';
    }
    $origPos = \ftell($fh);
    \fseek($fh, $offset);
    $privData = \fread($fh, $length);
    \fseek($fh, $origPos);
    return $privData;
  }

  /**
   * Read all data and informations from a WOFF file.
   *
   * @see https://www.w3.org/TR/WOFF/
   *
   * @param string $path
   *   Path for the input file.
   *
   * @return array
   *   Font data (with keys 'headers', 'entries' & 'fontTbl')
   */
  protected static function woffReader(string $path): array {
    if (!$fh = \fopen($path, 'rb')) {
      self::error(__METHOD__, "Wrong input: couldn't open WOFF file '$path'");
    }
    $headers = self::woffReadHeader($fh);
    $entries = self::woffReadTableDir($fh, $headers['numTables']);
    $fontTbl = self::woffReadFontTables($fh, $entries);
    $private = self::woffReadPrivData($fh, $headers['privOffset'], $headers['privLength']);
    \fclose($fh);
    return [
      'headers' => $headers,
      'entries' => $entries,
      'fontTbl' => $fontTbl,
      'private' => $private,
    ];
  }

  /**
   * Generate error message.
   *
   * @param string $method
   *   Class method name.
   * @param string $message
   *   Error message.
   */
  protected static function error(string $method, string $message) {
    throw new \Exception("[WoffConverter] $message");
  }

  /**
   * {@inheritdoc}
   */
  public function discoverIcons(): array {
    $path = "/path/to/bootstrap-icons.woff";
    // $private = "U+E000-U+EF8FF";
    $data = self::woffReader($path);
    // Woff is a wrapper around TrueType, OpenType, and Open Font Format.
    $tables = match ($data['headers']['type']) {
      // @todo call TtfExtractor.
      'ttf' => [],
      default => [],
    };
    return [];
  }

}
