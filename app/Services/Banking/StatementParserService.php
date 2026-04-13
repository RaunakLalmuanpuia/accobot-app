<?php

namespace App\Services\Banking;

use App\Agents\Narration\StatementDocumentParserAgent;
use App\DTOs\Banking\ParsedTransactionDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatementParserService
{
    public function __construct(
        private CsvExcelStatementParser $tabularParser,
    ) {}

    /**
     * Parse an uploaded bank statement file into a collection of ParsedTransactionDTOs.
     *
     * @return Collection<ParsedTransactionDTO>
     */
    public function parse(UploadedFile $file): Collection
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'pdf', 'jpg', 'jpeg', 'png', 'webp' => $this->parseVisualDocument($file),
            'csv', 'xlsx', 'xls', 'xlsm', 'tsv'  => $this->parseTabularFile($file),
            default => throw ValidationException::withMessages([
                'statement' => 'Unsupported file type. Please upload a PDF, CSV, or Excel file.',
            ]),
        };
    }

    // ── Visual Documents (PDF & Images) ────────────────────────────────────

    private function parseVisualDocument(UploadedFile $file): Collection
    {
        $response = StatementDocumentParserAgent::make()->prompt(
            'Extract all transaction rows from this attached bank statement.',
            attachments: [$file],
        );

        $jsonString = str_replace(['```json', '```'], '', $response->text);
        $parsed     = json_decode(trim($jsonString), true);
        $rows       = $parsed['transactions'] ?? [];

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'statement' => 'No transactions could be extracted from the document. Please check the file.',
            ]);
        }

        return collect($rows)->map(fn (array $row) => $this->rowToDto($row));
    }

    // ── CSV / Excel ────────────────────────────────────────────────────────

    private function parseTabularFile(UploadedFile $file): Collection
    {
        $result = $this->tabularParser->parse(
            $file->getRealPath(),
            $file->getClientOriginalExtension()
        );

        if (!empty($result['error'])) {
            throw ValidationException::withMessages([
                'statement' => $result['error'],
            ]);
        }

        $transactions = $result['transactions'] ?? [];

        if (empty($transactions)) {
            throw ValidationException::withMessages([
                'statement' => 'No transactions found in the uploaded file.',
            ]);
        }

        return collect($transactions)
            ->map(function ($row) {
                $type = match (strtolower($row['type'] ?? 'paid')) {
                    'received' => 'credit',
                    'paid'     => 'debit',
                    default    => 'debit',
                };

                $description = $row['description'] ?? 'Unknown';

                return ParsedTransactionDTO::fromArray([
                    'raw_narration'    => $description,
                    'type'             => $type,
                    'amount'           => (float) $row['amount'],
                    'bank_reference'   => '',
                    'party_name'       => $this->extractPartyFromDescription($description),
                    'transaction_date' => $row['date'],
                    'balance_after'    => null,
                    'bank_name'        => null,
                ]);
            })
            ->filter()
            ->values();
    }

    /**
     * Best-effort party name extraction from a CSV description column.
     *
     * CSV descriptions are structured (unlike raw SMS), so we can reliably
     * parse common Indian bank formats and populate party_name without an AI call.
     * This lets the rule engine match learned rules (e.g. "suntech solutions")
     * on CSV imports, not just SMS/email imports.
     */
    private function extractPartyFromDescription(string $description): ?string
    {
        // NEFT/IMPS/RTGS with txn ID: "NEFT/CR/2847263/SUNTECH SOLUTIONS/HDFC"
        if (preg_match('/(?:NEFT|IMPS|RTGS)[\/\s](?:CR|DR)[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i', $description, $m)) {
            return Str::title(strtolower(trim($m['party'])));
        }

        // NEFT dash-separated (no txn ID): "NEFT CR-SUNTECH SOLUTIONS-INV001-HDFC"
        if (preg_match('/(?:NEFT|IMPS|RTGS)[\s\-](?:CR|DR)[\s\-](?P<party>[A-Z][A-Z\s\.]+)[\s\-]/i', $description, $m)) {
            return Str::title(strtolower(trim($m['party'])));
        }

        // UPI with txn ID: "UPI/DR/123456/ZOMATO INDIA/KKBK/zomato@kotak"
        if (preg_match('/UPI[\/\s](?:CR|DR)[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i', $description, $m)) {
            return Str::title(strtolower(trim($m['party'])));
        }

        return null;
    }

    private function rowToDto(array $row): ParsedTransactionDTO
    {
        return ParsedTransactionDTO::fromArray([
            'raw_narration'    => $row['raw_narration'] ?? 'Unknown',
            'type'             => $row['type'],
            'amount'           => $row['amount'],
            'bank_reference'   => $row['bank_reference'] ?? '',
            'party_name'       => $row['party_name'] ?? null,
            'transaction_date' => $row['date'],
            'balance_after'    => $row['balance_after'] ?? null,
            'bank_name'        => null,
        ]);
    }
}
