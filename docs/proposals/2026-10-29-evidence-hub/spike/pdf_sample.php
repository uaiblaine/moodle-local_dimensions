<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * S0 spike: one dossier page per worker with core's TCPDF (freesans + QR), then N of them in a ZIP.
 *
 * Throwaway, never shipped (docs/ is export-ignored). Run inside a stack:
 * php <this file> [workers=50] [outdir=/tmp/evh-spike]
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../../config.php');
require_once($CFG->libdir . '/pdflib.php');

$workers = (int) ($argv[1] ?? 50);
$outdir = $argv[2] ?? '/tmp/evh-spike';
// Freesans embeds a subset of a TrueType font; helvetica is a PDF core font (Latin-1, nothing embedded).
define('LOCAL_DIMENSIONS_SPIKE_FONT', $argv[3] ?? 'freesans');
check_dir_exists($outdir);

/**
 * Build one worker's page and return the PDF bytes.
 *
 * @param int $n Worker number, used for example data.
 * @return string
 */
function local_dimensions_spike_page(int $n): string {
    $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->SetFont(LOCAL_DIMENSIONS_SPIKE_FONT, '', 9);
    $pdf->AddPage();
    $rows = '';
    $comps = ['NR-10 Segurança em eletricidade', 'NR-12 Máquinas e equipamentos', 'NR-33 Espaço confinado',
        'NR-35 Trabalho em altura', 'ASO periódico', 'Leitura de projetos elétricos', 'Segurança comportamental'];
    foreach ($comps as $i => $name) {
        // TCPDF lays out each row on its own: every cell needs its width, or columns drift from the header.
        $rows .= '<tr><td width="28%">' . s($name) . '</td><td width="20%">Certificado de treinamento</td>'
            . '<td width="14%">SENAI-SP</td><td width="13%">2025-0' . ($i + 1) . '-10</td>'
            . '<td width="13%">2027-0' . ($i + 1) . '-10</td><td width="12%">Conforme</td></tr>';
    }
    $html = '<h2>Dossiê de prontidão — Linha 4, Contrato 118/2026</h2>'
        . '<p><b>Colaborador:</b> João Conceição da Silva Araújo · <b>ID:</b> ' . (40000 + $n)
        . ' · <b>Emitido:</b> 2026-10-29 · <b>Situação:</b> APTO</p>'
        . '<table border="1" cellpadding="3"><thead><tr style="background-color:#f1f3f5;font-weight:bold">'
        . '<th width="28%">Competência</th><th width="20%">Evidência</th><th width="14%">Emissor</th>'
        . '<th width="13%">Emissão</th><th width="13%">Validade</th><th width="12%">Situação</th></tr></thead><tbody>'
        . $rows . '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $code = bin2hex(random_bytes(16));
    $style = ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false];
    $url = 'https://m503.example/local/evidencehub/verify.php?c=' . $code;
    $pdf->write2DBarcode($url, 'QRCODE,M', 15, 240, 32, 32, $style, 'N');
    $pdf->SetXY(52, 242);
    $pdf->MultiCell(140, 0, "Verifique este dossiê pelo código ao lado.\nDossiê " . strtoupper($code), 0, 'L');
    return $pdf->Output('', 'S');
}

$start = microtime(true);
$one = local_dimensions_spike_page(1);
file_put_contents($outdir . '/sample.pdf', $one);
$first = microtime(true) - $start;

// The writer takes a file NAME: it creates the ZIP in a request directory (gone when the request ends)
// and tells where through get_path_to_zip(). An absolute path makes fopen() fail and ZipStream then
// writes to stdout without a word.
$writer = \core_files\archive_writer::get_file_writer('sample.zip', \core_files\archive_writer::ZIP_WRITER);
$start = microtime(true);
for ($n = 1; $n <= $workers; $n++) {
    $writer->add_file_from_string('dossie/' . (40000 + $n) . '/' . (40000 + $n) . '-dossier.pdf', local_dimensions_spike_page($n));
}
$writer->finish();
$all = microtime(true) - $start;
$zippath = $outdir . '/sample.zip';
copy($writer->get_path_to_zip(), $zippath);

mtrace(sprintf('one page: %.0f ms, %d bytes, sha256 %s', $first * 1000, strlen($one), hash('sha256', $one)));
$peak = memory_get_peak_usage(true) / 1048576;
mtrace(sprintf(
    '%d workers: %.2f s total, %.0f ms per worker, zip %d bytes, peak memory %.1f MB',
    $workers,
    $all,
    $all * 1000 / $workers,
    filesize($zippath),
    $peak
));
