{{-- Compatibilité : conserver l'ancien include en délégant vers le nouveau partial de champs. --}}
@include('admin.events.manage._sponsor_fields', ['formId' => $formId, 'sponsor' => $sponsor])
