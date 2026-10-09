@php
    use App\Enums\RfpStatus;

    $meanings = [
        RfpStatus::Draft->value => 'Saved but not submitted. Only you can see and edit it.',
        RfpStatus::ForApReview->value => 'Submitted. The AP Processor checks that it is complete (Step 2).',
        RfpStatus::ForApproval->value => 'With the business approvers from the matrix (Step 3).',
        RfpStatus::ForApProcessing->value => 'Approved by the business. The AP Processor assigns tax codes and decides (Step 4).',
        RfpStatus::ForRevision->value => 'Sent back to you with remarks. Edit it and resubmit.',
        RfpStatus::ForAccountingApproval->value => 'With the AP Supervisor, then the AP Manager (Step 5). Either can return it to the AP Processor.',
        RfpStatus::ForTreasury->value => 'With Treasury for release (Step 6).',
        RfpStatus::Completed->value => 'Done. Treasury has completed the request.',
        RfpStatus::Cancelled->value => 'Cancelled, with the reason recorded in the history. No further action.',
    ];
@endphp

<table>
    <thead>
        <tr>
            <th>Status</th>
            <th>What it means</th>
        </tr>
    </thead>
    <tbody>
        @foreach (RfpStatus::cases() as $status)
            <tr>
                <td class="not-prose"><x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge></td>
                <td>{{ $meanings[$status->value] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
