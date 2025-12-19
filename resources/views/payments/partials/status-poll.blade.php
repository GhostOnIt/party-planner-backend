<div id="payment-status-container"
     data-status="{{ $payment->status }}"
     data-is-completed="{{ $isCompleted ? 'true' : 'false' }}"
     data-is-failed="{{ $isFailed ? 'true' : 'false' }}">

    <div class="status-badge status-{{ $payment->status }}">
        {{ ucfirst($payment->status) }}
    </div>

    @if($statusInfo['message'] ?? null)
        <p class="status-message">{{ $statusInfo['message'] }}</p>
    @endif

    @if($isCompleted)
        <div class="alert alert-success">
            Paiement effectue avec succes !
        </div>
    @elseif($isFailed)
        <div class="alert alert-danger">
            Le paiement a echoue.
            <a href="{{ route('payments.retry', $payment) }}">Reessayer</a>
        </div>
    @else
        <div class="alert alert-info">
            Paiement en cours de traitement...
        </div>
    @endif
</div>
