<?php

declare(strict_types=1);

/*
 * Línea base de Larastan (nivel 5) tomada el 19/11/2026 en la CI: los avisos que ya había cuando se agregó el
 * análisis. La CI falla con cualquier aviso nuevo y también cuando uno de estos desaparece sin quitarlo de aquí,
 * para que la lista solo baje. Se vuelve a generar con:
 *   vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline=phpstan-baseline.php
 */

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/PrepareInstallmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Booking\\WaitlistService::freedSlotNotice() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Booking\\WaitlistService::freedSlotNotice() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Console/Commands/ReleaseExpiredWaitlistHolds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Enums\\TreatmentKind::for() should return App\\Enums\\TreatmentKind but returns string.',
    'count' => 1,
    'path' => __DIR__.'/app/Enums/TreatmentKind.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $keys of method Illuminate\\Support\\Collection<(int|string),Illuminate\\Support\\Collection<int, App\\Models\\Treatment>>::except() expects array<(int|string)>|Illuminate\\Support\\Enumerable<(int|string), (int|string)>|string, int given.',
    'count' => 1,
    'path' => __DIR__.'/app/Enums/TreatmentKind.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 3,
    'path' => __DIR__.'/app/Exceptions/BookingException.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 5,
    'path' => __DIR__.'/app/Exceptions/BookingException.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Exceptions/BookingException.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$aftercare.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$closed_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$user_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::durationFor().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::pending().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\AppointmentStatus::CheckedIn, App\\Enums\\AppointmentStatus::InProgress} and true will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\AppointmentStatus::InProgress, App\\Enums\\AppointmentStatus::Completed} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\AppointmentStatus::Pending, App\\Enums\\AppointmentStatus::Confirmed, App\\Enums\\AppointmentStatus::CheckedIn} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, list<App\\Enums\\AppointmentStatus> and true will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 8,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Comparison operation ">" between 0 and 1 is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Actions\\AppointmentActions::openConsultation() should return App\\Models\\Consultation|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $consultation of static method App\\Filament\\Resources\\Consultations\\ConsultationResource::urlFor() expects App\\Models\\Consultation, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\RescheduleRequests\\RescheduleRequestResource::forPatientUrl() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\AppointmentStatus::Cancelled will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::CheckedIn will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Completed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::InProgress will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/AppointmentActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::usualPaymentMethod().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::chargeDueInstallments() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::registerPayment() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::unpaidCharges() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::upcomingInstallment() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Completed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CheckoutAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/CreateAppointmentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string|null and App\\Enums\\Sex::Male will always evaluate to true.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/EditMedicalBackgroundAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$created_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/IncludeInPackageAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$balance_due.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::checkup() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::suggestedNextVisit() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::upcomingVisit() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\Appointments\\AppointmentResource::nextVisitUrl() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->doctor_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/RegisterPaymentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/SendWhatsAppAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SendWhatsAppAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->full_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SendWhatsAppAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #4 $doctor of method App\\Services\\Clinical\\ConsentService::render() expects App\\Models\\Doctor|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignConsentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Variable $consent might not be defined.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignConsentAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$document_number.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignPlanAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignPlanAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignPlanAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Clinical\\ConsentService::defaultSigner() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignPlanAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/SignPlanAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Actions/WalkInAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Actions/WalkInAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of static method App\\Filament\\Actions\\WalkInAction::headline() expects App\\Models\\Appointment, null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/WalkInAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $appointment of static method App\\Filament\\Support\\ArrivalChecklist::addTo() expects App\\Models\\Appointment, null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Actions/WalkInAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $treatment of static method App\\Filament\\Actions\\SignConsentAction::make() expects (Closure(): (App\\Models\\Treatment|null))|null, Closure(array): (Illuminate\\Database\\Eloquent\\Model|null) given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Concerns/HasPatientSummaryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (array{0: Filament\\Forms\\Components\\TextInput, 1: Filament\\Forms\\Components\\TextInput, 2: Filament\\Forms\\Components\\TextInput, 3?: Filament\\Forms\\Components\\DatePicker}) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Forms/PaymentTermsFields.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Appointment::$occupied_until.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$balance_due.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$short_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::withBalance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection<int, App\\Models\\Doctor>::modelKeys().',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\AppointmentStatus::Pending, App\\Enums\\AppointmentStatus::Confirmed, App\\Enums\\AppointmentStatus::CheckedIn, App\\Enums\\AppointmentStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $value on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gt() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Empty array passed to foreach.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\Appointment>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $cards of static method App\\Filament\\Pages\\Agenda::inLanes() expects Illuminate\\Support\\Collection<int, array<string, mixed>>, Illuminate\\Support\\Collection<int, array{id: mixed, start: int, end: int, until: int, time: string, startTime: mixed, patient: mixed, shortName: mixed, ...}> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method Illuminate\\Database\\Eloquent\\Builder<App\\Models\\ScheduleBlock>::affecting() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::for() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::isSevere() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $time of method App\\Filament\\Pages\\Agenda::minuteOf() expects DateTimeInterface, string given.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $time of method App\\Filament\\Pages\\Agenda::minuteOf() expects DateTimeInterface, string|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $from of method Illuminate\\Database\\Eloquent\\Builder<App\\Models\\ScheduleBlock>::affecting() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $to of method Illuminate\\Database\\Eloquent\\Builder<App\\Models\\ScheduleBlock>::affecting() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Agenda.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\DoctorSchedule): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/Auth/EditProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/OwingBalances.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::owing().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Pages/OwingBalances.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/OwingBalances.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Filament\\Pages\\StockCount::$form.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Pages/StockCount.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/StockCount.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $value on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/StockCount.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Pages/StockCount.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $value of method Filament\\Support\\Components\\Component::evaluate() expects App\\Models\\Account|(callable(): (App\\Models\\Account|null))|null, (Closure(App\\Models\\Account): App\\Models\\Account)|(Closure(mixed): (App\\Models\\Account|null)) given.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/Accounts/AccountActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Accounts/RelationManagers/PatientsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Accounts/RelationManagers/PatientsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Accounts/Schemas/AccountInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Accounts/Tables/AccountsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::awaitingConfirmation().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ListAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::unclosed().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ListAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::awaitingConfirmation().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ListAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::unclosed().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ListAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ViewAppointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ViewAppointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Pages/ViewAppointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$accepted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Appointments\\Schemas\\AppointmentForm::newPatientFields() should return list<Filament\\Forms\\Components\\Field> but returns array{Filament\\Forms\\Components\\TextInput, Filament\\Forms\\Components\\TextInput, Filament\\Forms\\Components\\TextInput, Filament\\Forms\\Components\\Select, Filament\\Forms\\Components\\DatePicker, Filament\\Schemas\\Components\\Grid, Filament\\Forms\\Components\\Radio}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $plan of static method App\\Services\\Clinical\\TreatmentPlanService::paymentModes() expects App\\Models\\TreatmentPlan, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 9,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$promo_code.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::valid().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, list<App\\Enums\\AppointmentStatus> and true will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $value on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getColor() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 11,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Expression on left side of ?? is not nullable.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between null and \'cancelled\' is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between null and \'checked_in\' is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between null and \'confirmed\' is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between null and \'no_show\' is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::Cancelled is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::NoShow is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Appointments\\Schemas\\AppointmentInfolist::closedAs() never returns array{label: string, detail: string|null, color: string} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Appointments\\Schemas\\AppointmentInfolist::messageRows() should return Illuminate\\Support\\Collection<int, array{at: Carbon\\CarbonInterface, label: string, who: string|null, notes: string|null, tone: string}> but returns Illuminate\\Support\\Collection<int, array{at: Carbon\\Carbon|string|null, label: string, who: null, notes: null, tone: string}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Appointments\\Schemas\\AppointmentInfolist::paymentRows() should return Illuminate\\Support\\Collection<int, array{at: Carbon\\CarbonInterface, label: string, who: string|null, notes: string|null, tone: string}> but returns Illuminate\\Support\\Collection<int, array{at: string, label: string, who: mixed, notes: null, tone: string}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Negated boolean expression is always true.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'step\' on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array of function array_values contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() contains unresolvable type.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: Carbon\\Carbon, label: string, who: mixed, notes: string|null, tone: string}, Closure(App\\Models\\AppointmentEvent): array{at: Carbon\\Carbon, label: string, who: mixed, notes: string|null, tone: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: Carbon\\Carbon|string|null, label: non-falsy-string, who: null, notes: null, tone: \'warning\'}, Closure(App\\Models\\WhatsappMessage): array{at: Carbon\\Carbon|string|null, label: non-falsy-string, who: null, notes: null, tone: \'warning\'} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: string, label: non-falsy-string, who: mixed, notes: null, tone: \'success\'}, Closure(App\\Models\\Payment): array{at: string, label: non-falsy-string, who: mixed, notes: null, tone: \'success\'} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{label: non-falsy-string, amount: string, url: string}, Closure(App\\Models\\Payment): array{label: non-falsy-string, amount: string, url: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{title: mixed, when: string, status: mixed, color: mixed, url: string|null, current: bool}, Closure(App\\Models\\Appointment): array{title: mixed, when: string, status: mixed, color: mixed, url: string|null, current: bool} given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,array<string, mixed>>::sortBy() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\PatientSummary::viewData() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $startsAt of static method App\\Filament\\Resources\\Appointments\\Schemas\\AppointmentInfolist::howFarIs() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 ...$values of method Illuminate\\Support\\Collection<int,array<string, mixed>>::push() contains unresolvable type.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 ...$values of method Illuminate\\Support\\Collection<int,array<string, mixed>>::push() expects array{at: Carbon\\Carbon, label: string, who: mixed, notes: string|null, tone: string}, array{at: Carbon\\CarbonInterface|string, label: string, who: mixed, notes: string|null, tone: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 ...$values of method Illuminate\\Support\\Collection<int,array<string, mixed>>::push() expects array{at: Carbon\\Carbon, label: string, who: mixed, notes: string|null, tone: string}, array{at: null, label: \'Cita registrada\', who: mixed, notes: null, tone: \'gray\'} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Completed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Sent will always evaluate to false.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Ternary operator condition is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe method call on non-nullable type Carbon\\CarbonInterface. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$balance_due.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::insideABlock().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isCurrentYear() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Appointments/Tables/AppointmentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/AuditLogs/AuditLogResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $relations of method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::with() expects \'auditable\'|array<\'auditable\'|int, array<mixed>|(Closure(Illuminate\\Database\\Eloquent\\Relations\\Relation<*, *, *>): mixed)|string>, array{0: \'user\', auditable: Closure(Illuminate\\Database\\Eloquent\\Relations\\MorphTo): Illuminate\\Database\\Eloquent\\Relations\\MorphTo} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/AuditLogs/AuditLogResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/AuditLogs/AuditLogResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Amount is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Percent is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Campaigns/Tables/CampaignsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'body\' does not exist on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'body\' on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'version\' on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{number: int, until: non-falsy-string, by: mixed, body: string}, Closure(App\\Models\\AuditLog, int): array{number: int, until: non-falsy-string, by: mixed, body: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\AuditLog): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ConsentTemplates/ConsentTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/ConsultationResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$icd10_code_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tooth.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Clinical\\TreatmentPlanService::workableItems() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Actions\\SendToLabAction::make() expects Closure(): App\\Models\\Patient, Closure(): (Illuminate\\Database\\Eloquent\\Model|null) given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Actions\\SignConsentAction::make() expects Closure(): App\\Models\\Patient, Closure(): (Illuminate\\Database\\Eloquent\\Model|null) given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $patient of method App\\Services\\Clinical\\PrescriptionService::fromTemplateFor() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $value of method Illuminate\\Support\\Collection<(int|string),mixed>::put() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/EditConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ListConsultations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ListConsultations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Actions\\SendToLabAction::make() expects Closure(): App\\Models\\Patient, Closure(): (Illuminate\\Database\\Eloquent\\Model|null) given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\Appointments\\AppointmentResource::nextVisitUrl() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $prescription of method App\\Services\\Clinical\\PrescriptionService::whatsappDraft() expects App\\Models\\Prescription, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $prescription of static method App\\Filament\\Resources\\Consultations\\ConsultationResource::prescriptionPrintUrl() expects App\\Models\\Prescription, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Pages/ViewConsultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$accepted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$age.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$campaign.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$consultation.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$description.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medicalBackground.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_note.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$title.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tooth.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::open().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isChild().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Consultations\\Schemas\\ConsultationForm::savedPrescription() should return App\\Models\\Prescription|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Clinical\\ConsentService::signedForVisit() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $package of static method App\\Services\\Clinical\\TreatmentPlanService::controlEvery() expects App\\Models\\TreatmentPackage, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Clinical\\TreatmentPlanService::workableItems() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\Consultations\\Schemas\\ConsultationForm::medicationWarnings() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\Patients\\Schemas\\TreatmentPlanForm::companionSelect() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::withSeverity() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Services\\Clinical\\TreatmentRisks::for() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $package of static method App\\Filament\\Resources\\Consultations\\Schemas\\ConsultationForm::coveredByPackage() expects App\\Models\\TreatmentPackage, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $patient of method App\\Services\\Clinical\\PrescriptionService::fromPrescriptionFor() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->attended_at" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->conditions" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->description" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->price_from" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$code.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$description.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$items.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\TreatmentPlanItem): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\Treatment): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::callout() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe method call on non-nullable type string. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Schemas/ConsultationInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$code.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isBefore() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\ConsultationDiagnosis): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->code" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Consultations/Tables/ConsultationsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of static method App\\Filament\\Resources\\Doctors\\Schemas\\DoctorForm::missingToAttend() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Pages/CreateDoctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Pages/EditDoctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Pages/EditDoctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::outsideSchedule() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Pages/EditDoctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of static method App\\Filament\\Resources\\Doctors\\Schemas\\DoctorForm::applyPanelAccess() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Pages/EditDoctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Treatment::$pivot.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/RelationManagers/TreatmentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::durationFor().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/RelationManagers/TreatmentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function is_array() with array<string, mixed> will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Schemas/DoctorForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\DoctorSchedule): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Doctors/Tables/DoctorsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Expenses/ExpenseResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$title.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Installments/InstallmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$spent_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::needingAttention().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::withNextAppointment().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\LabOrders\\LabOrderResource::nextAppointment() should return App\\Models\\Appointment|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/LabOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::needingAttention().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/Pages/ManageLabOrders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::pending().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/Pages/ManageLabOrders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::needingAttention().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/Pages/ManageLabOrders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::pending().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/LabOrders/Pages/ManageLabOrders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method defaultBody() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/MessageTemplates/MessageTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/MessageTemplates/MessageTemplateResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::dueForCheckup().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ListPatients.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::missedAndNotRebooked().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ListPatients.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::dueForCheckup().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ListPatients.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::missedAndNotRebooked().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ListPatients.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewClinicalRecord.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewClinicalRecord.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::payments().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Patients\\Pages\\ViewPatient::summaryPatient() should return App\\Models\\Patient but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::chargeDueInstallments() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\PatientSummary::subheading() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Pages/ViewPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/PatientResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ChargesRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::addCharge() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ChargesRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::clinicalFiles().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ClinicalFilesRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::consents().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::upcoming().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsentsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::consultations().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/ConsultationsRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::treatmentPlans().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $items of static method App\\Models\\TreatmentPlan::lines() expects Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::contains() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model|int|string, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $odontogram of method App\\Services\\Clinical\\OdontogramSuggestions::planItems() expects App\\Models\\Odontogram|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::InProgress will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Ternary operator condition is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$attended_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$conditions.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_breastfeeding.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_pregnant.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$pregnancy_weeks.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string|null and App\\Enums\\Sex::Male will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->attended_at" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->conditions" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->starts_at" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/ClinicalRecordInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PatientRelationship::Self will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isBlocked().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isVerified().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::patients().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\Patients\\Schemas\\PatientInfolist::planRows() should return list<array{url: string, summary: string, total: string, status: App\\Enums\\TreatmentPlanStatus, since: string|null, payment: Illuminate\\Support\\HtmlString|null, open: bool}> but returns array<int, array{url: string, summary: string, total: string, status: string, since: string|null, payment: Illuminate\\Support\\HtmlString|null, open: bool}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/PatientInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/TreatmentPlanForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::patients().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Schemas/TreatmentPlanForm.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Tables/PatientsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Tables/PatientsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::withBalance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Tables/PatientsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Tables/PatientsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe method call on non-nullable type string. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Patients/Tables/PatientsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Payments/Tables/PaymentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/Payments/Tables/PaymentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Filament\\Tables\\Contracts\\HasTable::removeTableFilter().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Payments/Tables/PaymentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Payments/Tables/PaymentsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::open().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/Pages/ManagePurchaseOrders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Supplier::$low_count.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PlaceOrderAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::toReorder().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PlaceOrderAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tracks_serial.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$unit.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::hasWhatsApp().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::quantity().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isOpen() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\PurchaseOrderStatus::Partial is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\PurchaseOrderStatus::Received is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): Filament\\Schemas\\Components\\Fieldset, Closure(App\\Models\\PurchaseOrderItem): Filament\\Schemas\\Components\\Fieldset given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<array{received: float}>, Closure(App\\Models\\PurchaseOrderItem): non-empty-array<array{received: float}> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\PurchaseOrderItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::every() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model|string, Closure(App\\Models\\PurchaseOrderItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\RescheduleRequests\\Tables\\RescheduleRequestsTable::appointmentAsRequested() should return App\\Models\\Appointment but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\RescheduleRequestOption): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<int<0, max>, string>, Closure(App\\Models\\RescheduleRequestOption): non-empty-array<int<0, max>, string> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<string|null>, Closure(App\\Models\\RescheduleRequestOption): non-empty-array<string|null> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\RescheduleRequestOption): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::isFree() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::workingRanges() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $option of static method App\\Filament\\Resources\\RescheduleRequests\\Tables\\RescheduleRequestsTable::optionIsFree() expects App\\Models\\RescheduleRequestOption|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $date of method App\\Services\\Agenda\\AvailabilityService::workingRanges() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $option of method App\\Services\\Booking\\RescheduleRequestService::approve() expects App\\Models\\RescheduleRequestOption, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Agenda\\AvailabilityService::isFree() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\RescheduleRequestStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\RescheduleRequestStatus::Rejected will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'WeakMap<App\\Models\\RescheduleRequest, array<int, string|null>> does not accept array<string|null>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/RescheduleRequests/Tables/RescheduleRequestsTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/Pages/ManageScheduleBlocks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::upcoming().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/Pages/ManageScheduleBlocks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/Pages/ManageScheduleBlocks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\ScheduleBlock::$appointments_inside.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/ScheduleBlockResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/ScheduleBlockResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/ScheduleBlocks/ScheduleBlockResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/SmileCases/SmileCaseResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$expires_on.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$lot.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$serial.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$unit.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/StockMovements/StockMovementResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Suppliers/SupplierResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/Pages/ManageSupplies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::expiringSoon().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/Pages/ManageSupplies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::toReorder().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/Pages/ManageSupplies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/Pages/ViewSupply.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$status.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$supplier.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tracks_serial.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$unit.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::quantity().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::awaited().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $order of method App\\Services\\Inventory\\InventoryService::receiveOrder() expects App\\Models\\PurchaseOrder, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Treatment::$pivot.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\Treatment): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/SupplyResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Supplies/Tables/SuppliesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\TextSnippet::$last_used_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\SnippetStatus::Active will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\SnippetStatus::Archived will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\SnippetStatus::Proposed will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TextSnippets/TextSnippetResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPackages/TreatmentPackageResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::awaitingAnswer().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ListTreatmentPlans.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::open().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ListTreatmentPlans.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::unscheduled().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ListTreatmentPlans.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::awaitingAnswer().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ListTreatmentPlans.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::unscheduled().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ListTreatmentPlans.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ViewTreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ViewTreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::chargeDueInstallments() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Pages/ViewTreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\TreatmentPlan::$last_contacted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\TreatmentPlan::$next_appointment_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Proposed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->full_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanInfolist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\TreatmentPlan::$last_contacted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\TreatmentPlan::$next_appointment_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::Accepted, App\\Enums\\TreatmentPlanStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::Proposed, App\\Enums\\TreatmentPlanStatus::Accepted, App\\Enums\\TreatmentPlanStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Left side of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::Accepted is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::Cancelled is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::Completed is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::Draft is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::InProgress is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\TreatmentPlanStatus::Proposed is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match expression does not handle remaining value: string',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\TreatmentPlans\\Tables\\TreatmentPlansTable::followUpTemplate() never returns App\\Enums\\MessageTemplateKey so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\TreatmentPlans\\Tables\\TreatmentPlansTable::openItems() should return Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem> but returns Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Negated boolean expression is always true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $items of static method App\\Models\\TreatmentPlan::lines() expects Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $proposedAt of static method App\\Filament\\Resources\\TreatmentPlans\\Tables\\TreatmentPlansTable::waiting() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $patient of method App\\Services\\Dashboard\\FollowUps::draft() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Proposed will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/TreatmentPlanResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Resources\\TreatmentPlans\\TreatmentPlanResource::forPatient() should return Illuminate\\Database\\Eloquent\\Collection<int, App\\Models\\TreatmentPlan> but returns Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/TreatmentPlanResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlan): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/TreatmentPlanResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sortBy() expects array<array{string, \'asc\'|\'desc\'|SortDirection}|(callable(Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Model): mixed)|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|string>|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|int|string, Closure(App\\Models\\TreatmentPlan): (0|1) given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/TreatmentPlans/TreatmentPlanResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Supply::$pivot.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Treatments/TreatmentRecipeAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{supply_id: mixed, quantity: float}, Closure(App\\Models\\Supply): array{supply_id: mixed, quantity: float} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Treatments/TreatmentRecipeAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Treatment::$has_doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Treatments/TreatmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Treatment::$has_web_doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/Treatments/TreatmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Resources/Treatments/TreatmentResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Pages/ManageWaitlistEntries.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::closed().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Pages/ManageWaitlistEntries.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Pages/ManageWaitlistEntries.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\WaitlistEntry::$upcoming_appointment_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::availableOn().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffForHumans() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isAfter() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method App\\Filament\\Resources\\WaitlistEntries\\Tables\\WaitlistEntriesTable::when() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method App\\Filament\\Resources\\WaitlistEntries\\Tables\\WaitlistEntriesTable::when() expects Carbon\\CarbonInterface, string|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\WaitlistStatus::Booked will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\WaitlistStatus::Offered will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\WaitlistStatus::Removed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/Tables/WaitlistEntriesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\WaitlistEntry::$upcoming_appointment_id.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryActions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WaitlistEntries/WaitlistEntryResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::pending().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::promotions().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::failedRecently().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::pending().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::promotions().',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Pages/ListWhatsappMessages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::search().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\MessageStatus::Cancelled will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Failed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Sent will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->full_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/Tables/WhatsappMessagesTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Resources/WhatsappMessages/WhatsappMessageResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ArrivalChecklist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ArrivalChecklist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ArrivalChecklist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$key.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'file\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'key\' on array{}|string on left side of ?? does not exist.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'name\' on array{}|string on left side of ?? does not exist.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->key" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 5,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->new_values" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->old_values" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ChangeHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$allergies.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$filled_by_patient_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_breastfeeding.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_pregnant.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medications.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$pregnancy_weeks.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/ClinicalAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::awaitingConfirmation().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/MenuPending.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::pending().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffForHumans() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isTomorrow() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::guardian() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::guardian() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: \'Falta: apoderado\', color: \'warning\', icon: \'heroicon-m-user…\', url: string|null}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::labWork() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::labWork() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: non-falsy-string, color: \'danger\'|\'info\'|\'warning\', icon: \'heroicon-m-beaker\', url: string}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::lastVisit() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::lastVisit() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: \'Aún no viene\', color: \'gray\', icon: \'heroicon-m-sparkles\', url: null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::lastVisit() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: non-falsy-string, color: \'gray\'|\'warning\', icon: \'heroicon-m-clock\', url: string|null}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::nextVisit() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} but returns array{label: \'Sin cita agendada\', color: \'gray\', icon: \'heroicon-m-calendar\', url: string}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::nextVisit() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} but returns array{label: non-falsy-string, color: \'info\', icon: \'heroicon-m-calendar…\', url: string}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::noShows() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::noShows() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: non-falsy-string, color: \'warning\', icon: \'heroicon-m-phone\', url: non-falsy-string|null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::origin() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::origin() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: \'Falta: ¿cómo nos…\', color: \'warning\', icon: \'heroicon-m-megaphone\', url: string}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::origin() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: non-falsy-string, color: \'gray\', icon: \'heroicon-m-megaphone\', url: null}.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::unfinishedPlan() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::unfinishedPlan() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: \'Plan a medias:…\', color: \'warning\', icon: \'heroicon-m…\', url: string}|array{label: \'Presupuesto sin…\', color: \'warning\', icon: \'heroicon-m…\', url: null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::usualPayment() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::usualPayment() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: non-falsy-string, color: \'gray\', icon: \'heroicon-m-credit…\', url: null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::usualVisit() never returns array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null} so it can be removed from the return type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientSummary::usualVisit() should return array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}|null but returns array{label: string, color: \'gray\', icon: \'heroicon-m-calendar\', url: null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $next of static method App\\Filament\\Support\\PatientSummary::nextVisit() expects App\\Models\\Appointment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::NoShow will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientSummary.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$icd10Code.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tooth.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getColor() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Filament\\Support\\PatientTimeline::for() should return Illuminate\\Support\\Collection<int, array{at: Carbon\\CarbonInterface, kind: string, icon: string, color: string, title: string, detail: string|null, url: string|null, receipt: string|null, ...}> but returns Illuminate\\Support\\Collection<int, non-empty-array>.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\WhatsappMessage>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: string, kind: \'Atención\', icon: \'heroicon-o-clipboard-document-check\', color: \'primary\'|\'warning\', title: \'Atención\'|\'Atención sin terminar\', detail: string, url: string}, Closure(App\\Models\\Consultation): array{at: string, kind: \'Atención\', icon: \'heroicon-o-clipboard-document-check\', color: \'primary\'|\'warning\', title: \'Atención\'|\'Atención sin terminar\', detail: string, url: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: string, kind: \'Cita\', icon: \'heroicon-o-calendar-days\', color: mixed, title: non-falsy-string, detail: string, url: string|null}, Closure(App\\Models\\Appointment): array{at: string, kind: \'Cita\', icon: \'heroicon-o-calendar-days\', color: mixed, title: non-falsy-string, detail: string, url: string|null} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{at: string, kind: \'Pago\', icon: \'heroicon-o-banknotes\', color: \'gray\'|\'success\', title: non-falsy-string, detail: string|null, url: null, receipt: string, payment: mixed}, Closure(App\\Models\\Payment): array{at: string, kind: \'Pago\', icon: \'heroicon-o-banknotes\', color: \'gray\'|\'success\', title: non-falsy-string, detail: string|null, url: null, receipt: string, payment: mixed} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/PatientTimeline.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->doctor_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->patient_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->treatment_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 6,
    'path' => __DIR__.'/app/Filament/Support/SlotPicker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$accepted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (array{0: array{label: \'Imprimir\', url: string}, 1?: array{label: \'WhatsApp\', action: \'sendPlan\'}}) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::suggestedNextVisit() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Patients\\PatientInsights::upcomingVisit() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Resources\\Appointments\\AppointmentResource::nextVisitUrl() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::InProgress will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitCloseOut.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Negated boolean expression is always true.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitFlags.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointments of static method App\\Filament\\Support\\VisitFlags::compute() expects Illuminate\\Database\\Eloquent\\Collection<int, App\\Models\\Appointment>, Illuminate\\Database\\Eloquent\\Collection<(int|string), Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitFlags.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $item of static method App\\Filament\\Support\\VisitFlags::session() expects App\\Models\\TreatmentPlanItem|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitFlags.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::withSeverity() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Support/VisitFlags.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,App\\Models\\Appointment>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $status of static method App\\Filament\\Widgets\\CashClosingCheck::unclosedHint() expects App\\Enums\\AppointmentStatus, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/CashClosingCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{id: int, time: string, name: string, detail: string, url: string}, Closure(App\\Models\\Appointment): array{id: int, time: string, name: string, detail: string, url: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/DayMomentCard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$visits_count.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 3,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Support\\HigherOrderCollectionProxy<string, Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>>::$items.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::needingAttention().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::unclosed().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection<int, App\\Models\\Patient>::modelKeys().',
    'count' => 4,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffForHumans() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\DeskNote>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Filament\\Widgets\\FollowUpsToday::row() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::NoShow will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/FollowUpsToday.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medicalBackground.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $item of static method App\\Filament\\Support\\VisitFlags::session() expects App\\Models\\TreatmentPlanItem|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::withSeverity() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Confirmed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string|null and App\\Enums\\AppointmentStatus::CheckedIn will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/NextPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/OpenConsultations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/OpenConsultations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\Consultation>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/OpenConsultations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (array{0: Filament\\Widgets\\StatsOverviewWidget\\Stat, 1: Filament\\Widgets\\StatsOverviewWidget\\Stat, 2: Filament\\Widgets\\StatsOverviewWidget\\Stat, 3?: Filament\\Widgets\\StatsOverviewWidget\\Stat}) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/Reports/EarningsStats.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::withBalance().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $value on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::awaitingConfirmation().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayStats.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::due().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/TodayStats.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/WaitingRoom.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Filament/Widgets/WaitingRoom.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::waitingNow().',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/WaitingRoom.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Filament/Widgets/WaitingRoom.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medicalBackground.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 4,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::guestIds().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::patients().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, list<App\\Enums\\AppointmentStatus> and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Http\\Controllers\\Api\\V1\\Account\\AccountController::plansOf() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\Web\\ReturningPatient::for() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\Appointment>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{id: int<0, max>, label: string, state: mixed, done: false}, Closure(App\\Models\\TreatmentPlanItem): array{id: int<0, max>, label: string, state: mixed, done: false} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Done will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Done will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Completed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/AccountController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/Concerns/ChangesAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/Concerns/ChangesAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/Concerns/ChangesAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toImmutable() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/Concerns/ChangesAppointments.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/HealthController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string|null and App\\Enums\\Sex::Male will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/HealthController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/HealthController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Ternary operator condition is always false.',
    'count' => 8,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/HealthController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Http\\Controllers\\Api\\V1\\Account\\LinkController::applyChange() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Http\\Controllers\\Api\\V1\\Account\\LinkController::changePicker() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Http\\Controllers\\Api\\V1\\Account\\LinkController::changeScreen() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Booking\\AppointmentChange::pendingRequest() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Booking\\AppointmentService::cancelByPatient() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Booking\\AppointmentService::confirmByPatient() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Account/LinkController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$accepts_promotions.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 4,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$origin.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$privacy_accepted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::guestIds().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::patients().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toImmutable() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Http\\Controllers\\Api\\V1\\Booking\\BookingController::originsToAsk() should return Illuminate\\Support\\Collection<int, App\\Enums\\AppointmentOrigin> but returns Illuminate\\Support\\Collection<int, App\\Enums\\AppointmentOrigin::Facebook|App\\Enums\\AppointmentOrigin::Google|App\\Enums\\AppointmentOrigin::Instagram|App\\Enums\\AppointmentOrigin::Other|App\\Enums\\AppointmentOrigin::Recommendation|App\\Enums\\AppointmentOrigin::TikTok|App\\Enums\\AppointmentOrigin::WalkIn|App\\Enums\\AppointmentOrigin::WhatsApp>.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of static method App\\Support\\CampaignText::price() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $campaign of static method App\\Support\\CampaignText::price() expects App\\Models\\Campaign|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Booking/BookingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$slug.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<(int|string),Illuminate\\Database\\Eloquent\\Collection<int, App\\Models\\Faq>>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<(int|string),array<string, mixed>>::sortBy() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of static method App\\Support\\SiteArt::keyFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/FaqController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\Web\\ReturningPatient::for() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/HomeController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$slug.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/PromotionController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::webPrice().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/PromotionController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\TreatmentPackageItem): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/PromotionController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Models\\Campaign::priceFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/PromotionController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::published().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/SmileCaseController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/TeamController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Api/V1/Site/TreatmentController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Billing/PaymentReceiptController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$diagnoses.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$entries.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medication.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$vital_signs.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{signature: App\\Models\\TreatmentPlanSignature, image: string|null}, Closure(App\\Models\\TreatmentPlanSignature): array{signature: App\\Models\\TreatmentPlanSignature, image: string|null} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $dentition of static method App\\Support\\Teeth::rows() expects App\\Enums\\Dentition, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $item of method App\\Services\\Clinical\\PrescriptionService::instructionsOf() expects App\\Models\\PrescriptionItem, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $odontogram of static method App\\Http\\Controllers\\Clinical\\PrintController::drawing() expects App\\Models\\Odontogram|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Draft will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->entries" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Controllers/Clinical/PrintController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 3,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (array{0: array{\'Consultorio\', mixed}, 1: array{\'Período\', string}, 2: array{\'Doctor\', string}, 3: array{\'Cobrado\', float}, 4: array{\'Realizado\', float}, 5: array{\'Pago promedio\', 0|float}, 6?: array{\'Por cobrar (a hoy)\', float}}) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\Payment>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Reports/EarningsExportController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Controllers/Site/AppointmentCalendarController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$privacy_accepted_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Requests/Api/V1/StoreBookingRequest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$id.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$relationship.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toImmutable() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\RescheduleRequestOption): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 3,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Confirmed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Pending will always evaluate to false.',
    'count' => 5,
    'path' => __DIR__.'/app/Http/Resources/V1/AppointmentCardResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/CampaignResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$slug.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/CampaignResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{slug: string, name: string}, Closure(App\\Models\\Treatment): array{slug: string, name: string} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/DoctorResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/ReviewResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/ReviewResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/SmileCaseResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$slug.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/SmileCaseResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medicalBackground.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/WelcomeResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Left side of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/WelcomeResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of closure expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/WelcomeResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Http/Resources/V1/WelcomeResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Ternary operator condition is always false.',
    'count' => 8,
    'path' => __DIR__.'/app/Http/Resources/V1/WelcomeResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Livewire\\OdontogramChart::$conditions.',
    'count' => 7,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Livewire\\OdontogramChart::$dictated.',
    'count' => 2,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Livewire\\OdontogramChart::$favorites.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Livewire\\OdontogramChart::$newEntryIds.',
    'count' => 2,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Livewire\\OdontogramChart::$odontogram.',
    'count' => 15,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$code.',
    'count' => 7,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection<int, App\\Models\\OdontogramEntry>::modelKeys().',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $value on string.',
    'count' => 4,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): string, Closure(App\\Models\\OdontogramEntry): string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<(int|string),Illuminate\\Support\\Collection<int, App\\Models\\OdontogramEntry>>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<(int|string),array<string, mixed>>::sortBy() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Support\\Collection<int,array<string, mixed>>::contains() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $odontogram of method App\\Livewire\\OdontogramChart::load() expects App\\Models\\Odontogram|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $value of function collect contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->entries" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->patient_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Livewire/OdontogramChart.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::recognized().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Account.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, list<App\\Enums\\AppointmentStatus> and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Models\\Appointment::logEvent() should return App\\Models\\AppointmentEvent but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of || is always true.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\AppointmentStatus::CheckedIn will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::Pending will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Appointment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Amount is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Campaign.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Percent is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Campaign.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::writeAudit().',
    'count' => 160,
    'path' => __DIR__.'/app/Models/Concerns/Auditable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model&object{pivot: Illuminate\\Database\\Eloquent\\Relations\\Pivot}::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$consultation_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 3,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Models\\Consultation::plannedWork() should return Illuminate\\Database\\Eloquent\\Collection<int, App\\Models\\TreatmentPlanItem> but returns Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'blood_pressure\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'heart_rate\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'pain\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'temperature\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'weight\' on array{}|string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $details of method App\\Enums\\TreatmentKind::summary() expects array<string, mixed>|null, string|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->treatment" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/Consultation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/DeskNote.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/DeskNote.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/DeskNote.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/DeskNote.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Relations\\Pivot::$duration_minutes.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Doctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Relations\\Pivot::$learned_minutes.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Doctor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$voided_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/LabOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffForHumans() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/LabOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/LabOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isBefore() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/LabOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isToday() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/LabOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/MagicLink.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 3,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::awaitingAnswer().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::missedAndNotRebooked().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::unscheduled().',
    'count' => 2,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::valid().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $age on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Patient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/PurchaseOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::quantity().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/PurchaseOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\PurchaseOrderItem): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/PurchaseOrder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::open().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/PurchaseOrderItem.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $second of method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::whereColumn() expects string|null, Illuminate\\Contracts\\Database\\Query\\Expression given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/PurchaseOrderItem.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 8,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isSameDay() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/ScheduleBlock.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 3,
    'path' => __DIR__.'/app/Models/Supply.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::expiringSoon().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Supply.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::awaited().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Supply.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::expired().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Supply.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::available().',
    'count' => 2,
    'path' => __DIR__.'/app/Models/SupplyBatch.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/SupplyBatch.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/SupplyBatch.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Doctor::$pivot.',
    'count' => 5,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Supply::$pivot.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): (array{doctor: string, minutes: int|null, source: \'lo que suele tardar\'|\'puesta en su ficha\'}|null), Closure(App\\Models\\Doctor): (array{doctor: string, minutes: int|null, source: \'lo que suele tardar\'|\'puesta en su ficha\'}|null) given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<array{quantity: mixed}>, Closure(App\\Models\\Supply): non-empty-array<array{quantity: mixed}> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/Treatment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPackage.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\TreatmentPackageItem): float given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPackage.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $groupBy of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::groupBy() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): (int|string|Stringable|UnitEnum))|list<Closure|string>|string, Closure(App\\Models\\TreatmentPackageItem): mixed given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPackage.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPackageItem.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$price_from.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPackageItem.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$description.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$diagnoses.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$for_two.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$package.',
    'count' => 3,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::durationText().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::Draft, App\\Enums\\TreatmentPlanStatus::Proposed, App\\Enums\\TreatmentPlanStatus::Accepted, App\\Enums\\TreatmentPlanStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::Draft, App\\Enums\\TreatmentPlanStatus::Proposed} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method hasSchedule() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Models\\TreatmentPlan::lines() should return Illuminate\\Support\\Collection<int, array{label: string, price: float, package: bool, contents: list<string>, items: Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>}> but returns Illuminate\\Support\\Collection<int, array{label: string, price: float, package: bool, contents: array<int, string>, items: Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): mixed, Closure(App\\Models\\TreatmentPlan): mixed given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlan): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\TreatmentPlanItem): float given.',
    'count' => 4,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to true.',
    'count' => 4,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Proposed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->description" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->diagnoses" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlan.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$installments.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlanInstallment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$payment_mode.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlanInstallment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\PlanItemStatus::Pending, App\\Enums\\PlanItemStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/TreatmentPlanItem.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\UserRole::Admin will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/User.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\UserRole::Doctor will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/User.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\UserRole::Reception will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/User.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\WaitlistStatus::Waiting, App\\Enums\\WaitlistStatus::Offered} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method endOfDay() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method includes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isAfter() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\WaitlistStatus::Offered will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Models/WaitlistEntry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::pending().',
    'count' => 2,
    'path' => __DIR__.'/app/Models/WhatsappMessage.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::updateClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Policies/ClinicalFilePolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::viewClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/ClinicalFilePolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::updateClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/ConsentPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::viewClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/ConsentPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::viewClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/ConsultationPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isOpen() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/PurchaseOrderPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/RescheduleRequestPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TextSnippetPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\SnippetStatus::Proposed will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TextSnippetPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\SnippetStatus::Proposed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TextSnippetPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::updateClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TreatmentPlanPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $model of method App\\Policies\\PatientPolicy::viewClinical() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TreatmentPlanPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TreatmentPlanPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Proposed will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/TreatmentPlanPolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Policies/WhatsappMessagePolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isBlocked().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/DeviceService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Access\\DeviceService::remember() should return App\\Models\\RememberedDevice but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/DeviceService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isVerified().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/MagicLinkService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Access/MagicLinkService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/PatientAccess.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/PatientAccess.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\Access\\DeviceService::forgetUnverified() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/PatientAccess.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\Access\\DeviceService::remember() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/PatientAccess.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->full_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Access/PatientAccess.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Appointment::$occupied_until.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::upcoming().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method Carbon\\CarbonImmutable::instance() expects DateTimeInterface, string given.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method Carbon\\CarbonImmutable::instance() expects DateTimeInterface, string|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::holds() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::occupyingAppointments() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method Illuminate\\Database\\Eloquent\\Builder<App\\Models\\ScheduleBlock>::affecting() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $date of method App\\Services\\Agenda\\AvailabilityService::workingRanges() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/AvailabilityService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Appointment::$occupied_until.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::bufferMinutes().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $start of method App\\Services\\Agenda\\GapFinder::overlap() expects DateTimeInterface, string given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $end of method App\\Services\\Agenda\\GapFinder::overlap() expects DateTimeInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $treatment of method App\\Services\\Booking\\WaitlistService::slotsFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/GapFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/LearnedDurations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $treatment of method App\\Services\\Agenda\\LearnedDurations::measure() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Agenda/LearnedDurations.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between array{} and array{} will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Backup/DatabaseBackup.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Backup/OffsiteCopies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'file\' does not exist on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Backup/OffsiteCopies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,App\\Models\\AuditLog>::mapWithKeys() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Backup/OffsiteCopies.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$campaign.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$in_payment_schedule.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$plan.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$price_from.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$sessions_done.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$sessions_planned.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$title.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment_package_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::owing().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::balance().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::discountOn().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::subtotal().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::valid().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{installment: App\\Models\\TreatmentPlanInstallment, state: \'due\'|\'late\'|\'paid\'|\'upcoming\', due: float}, Closure(App\\Models\\TreatmentPlanInstallment): array{installment: App\\Models\\TreatmentPlanInstallment, state: \'due\'|\'late\'|\'paid\'|\'upcoming\', due: float} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\TreatmentPlanItem): float given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $item of method App\\Services\\Billing\\BillingService::chargePlanSession() expects App\\Models\\TreatmentPlanItem, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $item of method App\\Services\\Billing\\BillingService::nextSession() expects App\\Models\\TreatmentPlanItem, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::addCharge() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::unpaidCharges() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Services\\Billing\\BillingService::charge() expects App\\Models\\Treatment|null, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Services\\Billing\\BillingService::charge() expects App\\Models\\Treatment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $appointment of method App\\Services\\Billing\\BillingService::chargePlanSession() expects App\\Models\\Appointment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #5 $appointment of method App\\Services\\Billing\\BillingService::charge() expects App\\Models\\Appointment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->doctor_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->treatment_id" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/BillingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$balance_due.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$charged_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 5,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::valid().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::net().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::valid().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,App\\Models\\Expense>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,App\\Models\\Payment>::map() contains unresolvable type.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property App\\Services\\Billing\\CashClosing::$pending (Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient, due: float, concepts: string}>|null) does not accept Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient, due: float<5.0E-324, max>, concepts: string}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property App\\Services\\Billing\\CashClosing::$pending (Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient, due: float, concepts: string}>|null) is never assigned Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient, due: float, concepts: string}> so it can be removed from the property type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->full_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 6,
    'path' => __DIR__.'/app/Services/Billing/CashClosing.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::charges().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getLabel() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Billing/PaymentReceipt.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\AppointmentChange::pendingRequest() should return App\\Models\\RescheduleRequest|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentChange.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Services\\Booking\\WebAvailability::days() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentChange.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Services\\Booking\\WebAvailability::slots() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentChange.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$created_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection<int, App\\Models\\Appointment>::modelKeys().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\WhatsApp\\WhatsAppOutbox::queueForAppointment() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointments of method App\\Services\\Booking\\AppointmentReminders::lastTold() expects Illuminate\\Support\\Collection<int, App\\Models\\Appointment>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\Appointment): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $groupBy of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::groupBy() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): (int|string|Stringable|UnitEnum))|list<Closure|string>|string, Closure(App\\Models\\Appointment): mixed given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $startsAt of method App\\Services\\Booking\\AppointmentReminders::dayBefore() expects Carbon\\CarbonInterface, string given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentReminders.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$aftercare.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 5,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 6,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::bufferMinutes().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, list<App\\Enums\\AppointmentStatus> and true will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string|null, array{App\\Enums\\ConfirmationChannel::Reception, App\\Enums\\ConfirmationChannel::Doctor} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isPast() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::Cancelled is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::CheckedIn is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::Confirmed is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\AppointmentStatus::NoShow is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match expression does not handle remaining values: App\\Enums\\AppointmentStatus::Completed|App\\Enums\\AppointmentStatus::InProgress|App\\Enums\\AppointmentStatus::Pending',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\AppointmentService::resolveWebPatient() should return App\\Models\\Patient but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\Patient): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::blockAt() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::clashAt() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::holdAt() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Booking\\AppointmentService::assertDoctorOffers() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Booking\\AppointmentService::lockAgenda() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Booking\\WaitlistService::freedSlotNotice() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Models\\Doctor::durationFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $doctor of method App\\Services\\Booking\\AppointmentService::assertCanManage() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 8,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $doctor of method App\\Services\\Booking\\AppointmentService::move() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Agenda\\AvailabilityService::blockAt() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Agenda\\AvailabilityService::clashAt() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Agenda\\AvailabilityService::holdAt() expects Carbon\\CarbonInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $startsAt of method App\\Services\\Booking\\WaitlistService::freedSlotNotice() expects Carbon\\CarbonInterface, string given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $treatment of method App\\Services\\Booking\\AppointmentService::assertDoctorOffers() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between null and null will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\AppointmentStatus::Pending will always evaluate to true.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string|null and App\\Enums\\CancelledBy::Clinic will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Booking/AppointmentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isChangeable() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/PatientChangePolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isFuture() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/PatientChangePolicy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$doctor.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$user.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::bufferMinutes().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::durationFor().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::logEvent().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::options().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\RescheduleRequestService::requestByPatient() should return App\\Models\\RescheduleRequest but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\RescheduleRequestService::withRequestedTreatment() is unused.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\RescheduleRequestService::withRequestedTreatment() should return App\\Models\\Appointment but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Booking\\AppointmentService::closeRescheduleRequests() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\WhatsApp\\WhatsAppOutbox::queueForAppointment() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $doctor of method App\\Services\\Agenda\\AvailabilityService::isFree() expects App\\Models\\Doctor, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of || is always true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\RescheduleRequestStatus::Pending will always evaluate to true.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/RescheduleRequestService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::bufferMinutes().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method Carbon\\CarbonImmutable::instance() expects DateTimeInterface, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of method App\\Models\\Doctor::durationFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $treatment of method App\\Services\\Booking\\WaitlistService::doctorsFor() expects App\\Models\\Treatment|null, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $treatment of method App\\Services\\Booking\\WaitlistService::slotsFor() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WaitlistService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WebAvailability.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WebAvailability.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Booking\\WebAvailability::slots() should return Illuminate\\Support\\Collection<int, array{time: string, doctor_id: int, recommended: bool, waste: int}> but returns Illuminate\\Support\\Collection<int, array{time: string, doctor_id: mixed, recommended: bool, waste: int}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Booking/WebAvailability.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$allergies.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/AllergyCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $relationship of static method App\\Services\\Clinical\\ConsentService::inverse() expects App\\Enums\\PatientRelationship, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #4 $doctor of method App\\Services\\Clinical\\ConsentService::render() expects App\\Models\\Doctor|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PatientRelationship::Self will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsentService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$plan.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$status.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::isOpen().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\AppointmentStatus::Pending, App\\Enums\\AppointmentStatus::Confirmed, App\\Enums\\AppointmentStatus::CheckedIn} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\ConsultationService::doctorOf() should return App\\Models\\Doctor but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset string does not exist on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset string on string on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Booking\\AppointmentService::markCompleted() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $appointment of method App\\Services\\Clinical\\ConsentService::signedForVisit() expects App\\Models\\Appointment, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Clinical\\ConsultationService::open() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Services\\Clinical\\PrescriptionService::warningsFor() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $patient of method App\\Services\\Clinical\\PrescriptionService::fromTemplateFor() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #4 $treatment of method App\\Services\\Clinical\\ConsultationService::open() expects App\\Models\\Treatment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/ConsultationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of || is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/FindingsDictation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between array{} and array{} will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/FindingsDictation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between null and null will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/FindingsDictation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->abbreviations" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/FindingsDictation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$conditions.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_pregnant.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$medications.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$pregnancy_weeks.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->conditions" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/MedicationCheck.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$allows_good_state.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$code.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::entries().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::odontograms().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::suggestedDentition().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\OdontogramService::forConsultation() should return App\\Models\\Odontogram but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\OdontogramService::prepare() should return array{array<string, mixed>, App\\Models\\OdontogramEntry|null} but returns array{array{odontogram_condition_id: mixed, tooth: int, surface: string|null, to_tooth: int|null, state: App\\Enums\\ToothState, abbreviation: string|null}, Illuminate\\Database\\Eloquent\\Model|null}.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\OdontogramService::save() should return App\\Models\\OdontogramEntry but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $dentition of static method App\\Support\\Teeth::rows() expects App\\Enums\\Dentition, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $dentition of static method App\\Support\\Teeth::exists() expects App\\Enums\\Dentition, string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\ToothState::Bad will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$code.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$icd10_code_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$slug.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tooth.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::entries().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection::load().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\PlanItemStatus::Pending, App\\Enums\\PlanItemStatus::InProgress} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $odontogram of method App\\Services\\Clinical\\OdontogramSuggestions::rows() expects App\\Models\\Odontogram|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Clinical\\TreatmentPlanService::workableItems() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between array{array{\'K08.1\', \'implante\'}} and null will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\ToothState::Bad will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/OdontogramSuggestions.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::items().',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\PrescriptionService::clean() should return Illuminate\\Support\\Collection<(int|string), array{medication: string, instructions: string|null}> but returns Illuminate\\Support\\Collection<(int|string), array{medication: non-empty-string, instructions: string|null}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\PrescriptionService::syncForConsultation() should return App\\Models\\Prescription|null but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'prescription_items\' on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{medication: string, instructions: string|null}, Closure(App\\Models\\PrescriptionItem): array{medication: string, instructions: string|null} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\PrescriptionItem): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 ...$values of method Illuminate\\Support\\Collection<int,string>::push() expects non-falsy-string, \'\' given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/PrescriptionService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$description.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$patient_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$sessions_planned.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$title.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 5,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment_id.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$voided_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::open().',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::companionPlans().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::items().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::net().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::nextSessionDays().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::open().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::Draft, App\\Enums\\TreatmentPlanStatus::Proposed} and true will always evaluate to false.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function in_array() with arguments string, array{App\\Enums\\TreatmentPlanStatus::InProgress, App\\Enums\\TreatmentPlanStatus::Completed} and true will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\TreatmentPlanService::addPackageToDraft() should return array{App\\Models\\TreatmentPlan|null, App\\Models\\TreatmentPlan|null} but returns array{Illuminate\\Database\\Eloquent\\Model|null, App\\Models\\TreatmentPlan|null}.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Clinical\\TreatmentPlanService::sign() should return App\\Models\\TreatmentPlanSignature but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array{number: int<0, max>, due_on: string, amount: float, discount: float}, Closure(App\\Models\\TreatmentPlanInstallment): array{number: int<0, max>, due_on: string, amount: float, discount: float} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<int<0, max>>, Closure(App\\Models\\TreatmentPackageItem): non-empty-array<int<0, max>> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::each() expects callable(Illuminate\\Database\\Eloquent\\Model, int): mixed, Closure(App\\Models\\TreatmentPlan): void given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPackageItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanInstallment): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::filter() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\TreatmentPackageItem): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\TreatmentPlanItem): float given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): int)|string|null, Closure(App\\Models\\TreatmentPackageItem): int given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|string|null, Closure(App\\Models\\TreatmentPlanInstallment): mixed given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $items of method App\\Services\\Clinical\\TreatmentPlanService::syncItems() expects iterable<App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $items of static method App\\Models\\TreatmentPlan::lines() expects Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::contains() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model|int|string, Closure(App\\Models\\TreatmentPlanItem): bool given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::every() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model|string, Closure(App\\Models\\TreatmentPlanItem): false given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::addCharge() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of method App\\Services\\Billing\\BillingService::chargeDueInstallments() expects App\\Models\\Patient|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $plan of method App\\Services\\Clinical\\TreatmentPlanService::addCompanion() expects App\\Models\\TreatmentPlan, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $plan of method App\\Services\\Clinical\\TreatmentPlanService::linkCampaign() expects App\\Models\\TreatmentPlan, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $plan of static method App\\Services\\Clinical\\TreatmentPlanService::awaitsPackageSignature() expects App\\Models\\TreatmentPlan, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of static method App\\Enums\\TreatmentKind::isControl() expects App\\Models\\Treatment|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $item of static method App\\Services\\Clinical\\TreatmentPlanService::scaledToEvery() expects App\\Models\\TreatmentPackageItem, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $items of method App\\Services\\Clinical\\TreatmentPlanService::lockItems() expects iterable<App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $items of static method App\\Services\\Clinical\\TreatmentPlanService::scheduledItems() expects Illuminate\\Support\\Collection<int, App\\Models\\TreatmentPlanItem>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $schedule of static method App\\Services\\Clinical\\TreatmentPlanService::scheduleText() expects list<array{number: int, due_on: Carbon\\CarbonInterface, amount: float, discount: float}>, array<int, array{number: int, due_on: string, amount: float, discount: float}> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Property App\\Models\\TreatmentPlanItem::$status (string) does not accept App\\Enums\\PlanItemStatus::Done|App\\Enums\\PlanItemStatus::InProgress|App\\Enums\\PlanItemStatus::Pending.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between int<0, max> and null will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to true.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to false.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Done will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Cancelled will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::Draft will always evaluate to false.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\TreatmentPlanStatus::InProgress will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentPlanService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$age.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$allergies.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$conditions.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_breastfeeding.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_pregnant.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$pregnancy_weeks.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$smoker.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->conditions" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Clinical/TreatmentRisks.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Dashboard/DayMoment.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::unscheduled().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::awaitingAnswer().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::unscheduled().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\WhatsApp\\WhatsAppOutbox::draftForAccount() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method Carbon\\CarbonImmutable::instance() expects DateTimeInterface, string|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Dashboard/FollowUps.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (list<\'fn_ahora_lima\'|\'fn_categoria…\'|\'fn_estado_cita\'|\'fn_hoy_lima\'|\'fn_medio_pago\'|\'fn_saldo_paciente\'|\'sp_cierre_caja\'|\'sp_reporte_mensual…\'|\'trg_pagos_auditar…\'|\'v_agenda_del_dia\'|\'v_cargos_pendientes\'|\'v_consumo_materiales\'|\'v_deuda_vencida\'|\'v_saldo_paciente\'>) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Database/DatabaseObjects.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Supply::$pivot.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$contact_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$phone.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$received_quantity.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$supply.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$tracks_serial.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::hasWhatsApp().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::quantity().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getTimestamp() on string.',
    'count' => 4,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isOpen() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Inventory\\InventoryService::firstToExpire() should return Illuminate\\Support\\Collection<int, App\\Models\\SupplyBatch> but returns Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Inventory\\InventoryService::receive() should return App\\Models\\SupplyBatch but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Inventory\\InventoryService::record() should return App\\Models\\StockMovement but returns Illuminate\\Database\\Eloquent\\Model.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::map() expects callable(Illuminate\\Database\\Eloquent\\Model, int): non-falsy-string, Closure(App\\Models\\PurchaseOrderItem): non-falsy-string given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::mapWithKeys() expects callable(Illuminate\\Database\\Eloquent\\Model, int): array<non-falsy-string>, Closure(App\\Models\\SupplyBatch): non-empty-array<non-falsy-string> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<(int|string),Illuminate\\Support\\Collection<int, array<string, float|int|null>>>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sort() expects (callable(Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Model): int)|int|null, Closure(App\\Models\\SupplyBatch, App\\Models\\SupplyBatch): int<-1, 1> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\Supply): float given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $key of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::every() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model|string, Closure(App\\Models\\PurchaseOrderItem): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $supply of method App\\Services\\Inventory\\InventoryService::record() expects App\\Models\\Supply, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $supply of method App\\Services\\Inventory\\InventoryService::take() expects App\\Models\\Supply, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #6 $batch of method App\\Services\\Inventory\\InventoryService::take() expects App\\Models\\SupplyBatch|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/InventoryService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'PHPDoc tag @var for constant App\\Services\\Inventory\\StarterCatalog::RECIPES with type array<string, array<string, float>> is not subtype of value array{evaluacion-o-primera-consulta: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Vasos descartables\': 1}, limpieza-dental: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Pasta profiláctica\': 5, \'Copas de profilaxis\': 1}, curacion-con-resina: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Lidocaína 2 % con epinefrina\': 1, \'Agujas dentales cortas\': 1, \'Ácido grabador 37 %\': 0.2, \'Adhesivo dental\': 0.1, \'Resina compuesta A2\': 0.5, ...}, extraccion-simple: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Lidocaína 2 % con epinefrina\': 2, \'Agujas dentales cortas\': 1, \'Gasas estériles\': 4, \'Sutura reabsorbible 4-0\': 1}, blanqueamiento: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Kit de blanqueamiento\': 1}, endodoncia: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Lidocaína 2 % con epinefrina\': 2, \'Agujas dentales cortas\': 1, \'Dique de goma\': 1, \'Hipoclorito de sodio 2.5 %\': 10, \'Conos de gutapercha\': 5}, colocacion-de-brackets: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Eyectores de saliva\': 1, \'Brackets metálicos\': 1, \'Arcos de níquel-titanio\': 2, \'Ligaduras elásticas\': 20}, control-de-ortodoncia: array{\'Guantes de nitrilo\': 1, \'Baberos descartables\': 1, \'Ligaduras elásticas\': 20}, ...}.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/StarterCatalog.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method toDateString() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/SupplyForecast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Inventory\\SupplyForecast::compute() should return Illuminate\\Support\\Collection<int, array{supply: App\\Models\\Supply, runs_out_on: Carbon\\CarbonImmutable|null, below_min_on: Carbon\\CarbonImmutable|null, needed: float}> but returns Illuminate\\Support\\Collection<(int|string), array{supply: App\\Models\\Supply, runs_out_on: Carbon\\CarbonImmutable|null, below_min_on: Carbon\\CarbonImmutable|null, needed: float<5.0E-324, max>}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/SupplyForecast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$expires_on.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$lot.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$serial.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::label().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method getTimestamp() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\SupplyBatch): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\SupplyBatch): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sortBy() expects array<array{string, \'asc\'|\'desc\'|SortDirection}|(callable(Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Model): mixed)|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|string>|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|int|string, Closure(App\\Models\\SupplyBatch): array{mixed, mixed, mixed} given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $patient of static method App\\Filament\\Support\\ClinicalAlerts::withSeverity() expects App\\Models\\Patient, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe method call on non-nullable type string. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Inventory/Tray.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$treatment.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::consultations().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::nextSessionDays().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::awaitingAnswer().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Relations\\HasMany::missedAndNotRebooked().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method copy() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Patients\\PatientInsights::lateInstallments() should return array{due: float, since: Carbon\\CarbonInterface}|null but returns array{due: float, since: string|null}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Patients\\PatientInsights::openConsultation() should return App\\Models\\Consultation|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Patients\\PatientInsights::upcomingInstallment() should return array{due: float, on: Carbon\\CarbonInterface}|null but returns array{due: float, on: string}|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Patients\\PatientInsights::upcomingVisit() should return App\\Models\\Appointment|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Patients\\PatientInsights::visitMissingConsent() should return App\\Models\\Appointment|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset \'blood_pressure\' on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Offset string on string|null on left side of ?? does not exist.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::first() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|null, Closure(App\\Models\\Appointment): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::flatMap() expects callable(Illuminate\\Database\\Eloquent\\Model, int): (array<int<0, max>, array{installment: App\\Models\\TreatmentPlanInstallment, state: \'due\'|\'late\'|\'paid\'|\'upcoming\', due: float}>|Illuminate\\Support\\Collection<int<0, max>, array{installment: App\\Models\\TreatmentPlanInstallment, state: \'due\'|\'late\'|\'paid\'|\'upcoming\', due: float}>), Closure(App\\Models\\TreatmentPlan): list<array{installment: App\\Models\\TreatmentPlanInstallment, state: \'due\'|\'late\'|\'paid\'|\'upcoming\', due: float}> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Patients/PatientInsights.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_visit_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$origin.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot access property $dayOfWeekIso on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMinutes() on string.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method isSameMonth() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method lte() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $date of static method Carbon\\CarbonImmutable::instance() expects DateTimeInterface, string given.',
    'count' => 3,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $callback of method Illuminate\\Support\\Collection<(int|string),int>::when() expects (callable(Illuminate\\Support\\Collection<int|string, int>, bool): Illuminate\\Support\\Collection<int|string, int|null>)|null, Closure(Illuminate\\Support\\Collection): Illuminate\\Support\\Collection<int|string, int|null> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\AppointmentStatus::NoShow will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/ActivityReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$amount.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/CampaignReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$discount.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Reports/CampaignReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Support\\HigherOrderCollectionProxy<string, Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model>>::$installments.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Reports/CampaignReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::flatMap() expects callable(Illuminate\\Database\\Eloquent\\Model, int): (array<int, Illuminate\\Database\\Eloquent\\Model>|Illuminate\\Support\\Collection<int, Illuminate\\Database\\Eloquent\\Model>), Closure(App\\Models\\TreatmentPlan): Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/CampaignReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sum() expects (callable(Illuminate\\Database\\Eloquent\\Model, int): float)|string|null, Closure(App\\Models\\TreatmentPlanItem): float given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Reports/CampaignReport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$price_from.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/MoneyWaiting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$sessions_planned.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/MoneyWaiting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::subtotal().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/MoneyWaiting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/MoneyWaiting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Reports\\MoneyWaiting::unansweredPlans() should return Illuminate\\Support\\Collection<int, array{plan: App\\Models\\TreatmentPlan, total: float, waiting_days: int, expired: bool}> but returns Illuminate\\Support\\Collection<int, array{plan: App\\Models\\TreatmentPlan, total: float<5.0E-324, max>, waiting_days: int, expired: bool}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Reports/MoneyWaiting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/StaffAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/StaffAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$user.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/StaffAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/StaffAlerts.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/OpeningHours.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (list<array<string, mixed>>) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/PageMeta.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Support\\Collection<int, App\\Models\\Patient>::modelKeys().',
    'count' => 2,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method diffInMonths() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Services\\Web\\ReturningPatient::sessions() should return Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient, done: int, total: int, doctor: string|null, requested: bool}> but returns Illuminate\\Support\\Collection<int, array{patient: App\\Models\\Patient|null, done: int, total: int, doctor: mixed, requested: bool}>.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Database\\Eloquent\\Collection<(int|string),Illuminate\\Database\\Eloquent\\Collection<int, App\\Models\\TreatmentPlan>>::map() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<(int|string),array<string, mixed>>::filter() contains unresolvable type.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::reject() expects bool|(callable(Illuminate\\Database\\Eloquent\\Model, int): bool)|Illuminate\\Database\\Eloquent\\Model, Closure(App\\Models\\Patient): bool given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $family of method App\\Services\\Web\\ReturningPatient::checkups() expects Illuminate\\Support\\Collection<int, App\\Models\\Patient>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $family of method App\\Services\\Web\\ReturningPatient::sessions() expects Illuminate\\Support\\Collection<int, App\\Models\\Patient>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $family of static method App\\Support\\FamilyNames::for() expects iterable<App\\Models\\Patient>, Illuminate\\Database\\Eloquent\\Collection<int, Illuminate\\Database\\Eloquent\\Model> given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between string and App\\Enums\\PlanItemStatus::Cancelled will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PlanItemStatus::Done will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/ReturningPatient.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Builder<Illuminate\\Database\\Eloquent\\Model>::active().',
    'count' => 1,
    'path' => __DIR__.'/app/Services/Web/SetupChecklist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/CampaignBroadcast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of method Illuminate\\Support\\Collection<int,Illuminate\\Database\\Eloquent\\Model>::sortBy() expects array<array{string, \'asc\'|\'desc\'|SortDirection}|(callable(Illuminate\\Database\\Eloquent\\Model, Illuminate\\Database\\Eloquent\\Model): mixed)|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|string>|(callable(Illuminate\\Database\\Eloquent\\Model, int): mixed)|int|string, Closure(App\\Models\\Patient): 1 given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/CampaignBroadcast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\PatientRelationship::Self will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/CampaignBroadcast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->first_name" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/CampaignBroadcast.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$account.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$before_instructions.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$full_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$starts_at.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method defaultBody() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method format() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method gt() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $account of method App\\Services\\Access\\MagicLinkService::issue() expects App\\Models\\Account, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $treatment of static method App\\Support\\CampaignText::price() expects App\\Models\\Treatment, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $campaign of static method App\\Support\\CampaignText::price() expects App\\Models\\Campaign|null, Illuminate\\Database\\Eloquent\\Model|null given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $appointment of method App\\Services\\Access\\MagicLinkService::issue() expects App\\Models\\Appointment|null, Illuminate\\Database\\Eloquent\\Model given.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Result of && is always false.',
    'count' => 2,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\MessageStatus::Sent will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Using nullsafe property access "?->body" on left side of ?? is unnecessary. Use -> instead.',
    'count' => 1,
    'path' => __DIR__.'/app/Services/WhatsApp/WhatsAppOutbox.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$first_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CalendarEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$name.',
    'count' => 2,
    'path' => __DIR__.'/app/Support/CalendarEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $moment of closure expects Carbon\\CarbonInterface, string given.',
    'count' => 2,
    'path' => __DIR__.'/app/Support/CalendarEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property Illuminate\\Database\\Eloquent\\Model::$is_active.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to an undefined method Illuminate\\Database\\Eloquent\\Model::trashed().',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cannot call method translatedFormat() on string.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Amount is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Match arm comparison between string and App\\Enums\\DiscountType::Percent is always false.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method App\\Support\\CampaignText::package() should return App\\Models\\TreatmentPackage|null but returns Illuminate\\Database\\Eloquent\\Model|null.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using !== between null and null will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Strict comparison using === between string and App\\Enums\\DiscountType::Percent will always evaluate to false.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/CampaignText.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Access to an undefined property App\\Models\\Patient::$short_name.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/FamilyNames.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array (list<mixed>) of array_values is already a list, call has no effect.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/Spreadsheet.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'PHPDoc tag @var for constant App\\Support\\ToothDrawing::SIZES with type array<string, array<int, array{float, float, float, float, int, string}>> is incompatible with value array{upper: array{1: array{7.5, 5.0, 30, 40, 1, \'incisal\'}, 2: array{6.0, 4.2, 27, 38, 1, \'incisal\'}, 3: array{7.0, 4.8, 31, 52, 1, \'canine\'}, 4: array{7.0, 5.0, 27, 42, 2, \'premolar\'}, 5: array{6.8, 5.0, 26, 42, 1, \'premolar\'}, 6: array{10.0, 8.0, 25, 40, 3, \'molar\'}, 7: array{9.3, 7.5, 24, 38, 3, \'molar\'}, 8: array{8.5, 7.0, 22, 32, 3, \'molar\'}}, lower: array{1: array{4.6, 3.3, 26, 38, 1, \'incisal\'}, 2: array{5.0, 3.5, 27, 40, 1, \'incisal\'}, 3: array{6.5, 4.6, 31, 48, 1, \'canine\'}, 4: array{6.8, 4.8, 26, 42, 1, \'premolar\'}, 5: array{7.0, 5.0, 26, 42, 1, \'premolar\'}, 6: array{10.5, 8.5, 25, 40, 2, \'molar\'}, 7: array{10.0, 8.0, 24, 38, 2, \'molar\'}, 8: array{9.5, 7.5, 23, 32, 2, \'molar\'}}, primaryUpper: array{1: array{6.0, 4.0, 22, 30, 1, \'incisal\'}, 2: array{5.0, 3.6, 21, 29, 1, \'incisal\'}, 3: array{6.0, 4.0, 24, 34, 1, \'canine\'}, 4: array{7.5, 6.0, 20, 28, 3, \'molar\'}, 5: array{9.0, 7.0, 21, 29, 3, \'molar\'}}, primaryLower: array{1: array{4.0, 2.9, 21, 29, 1, \'incisal\'}, 2: array{4.3, 3.1, 21, 29, 1, \'incisal\'}, 3: array{5.5, 3.8, 23, 32, 1, \'canine\'}, 4: array{7.5, 6.0, 20, 28, 2, \'molar\'}, 5: array{9.5, 7.5, 21, 29, 2, \'molar\'}}}.',
    'count' => 1,
    'path' => __DIR__.'/app/Support/ToothDrawing.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
