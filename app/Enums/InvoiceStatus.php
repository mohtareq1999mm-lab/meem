<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case GENERATED = 'generated';
    case PDF_GENERATING = 'pdf_generating';
    case READY = 'ready';
    case FAILED = 'failed';
    case CORRECTED = 'corrected';
    case CANCELLED = 'cancelled';
}
