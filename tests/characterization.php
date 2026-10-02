<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

function createBooking(
    string $customerType = 'standard',
    string $passType = 'day',
    float $price = 50.0,
    int $quantity = 1,
    ?string $phone = '0600000000'
): Booking {
    $customer = new Customer(1, 'test@example.com', $phone, $customerType);
    $ticket = new Ticket('TEST', 'Ticket test', $price);
    $booking = new Booking(1, $customer, $passType);
    $booking->addItem(new BookingItem($ticket, $quantity));
    return $booking;
}


$service = new BookingService();

$tests->run('confirmation standard', function () use ($tests, $service) {
    $standard = createBooking('standard', 'day', 50.0, 2);
    $standardTotal = $service->confirm($standard, 'stripe');
    $tests->near(100.0, $standardTotal, 'standard customer keeps initial total');
    $tests->same('confirmed', $standard->status, 'booking becomes confirmed');
});

$tests->run('confirmation VIP', function () use ($tests, $service) {
    $vip = createBooking('vip', 'day', 50.0, 2);
    $vipTotal = $service->confirm($vip, 'stripe');
    $tests->near(90.0, $vipTotal, 'legacy VIP rule gives 10 percent discount');
});

$tests->run('confirmation pass 3 jours', function () use ($tests, $service) {
    $threeDays = createBooking('standard', '3days', 60.0, 2);
    $threeDaysTotal = $service->confirm($threeDays, 'stripe');
    $tests->near(110.0, $threeDaysTotal, 'legacy three day pass discount is 10 euros');
});

$tests->run('confirmation VIP et pass 3 jours', function () use ($tests, $service) {
    $booking = createBooking('vip', '3days', 50.0, 2);

    $total = $service->confirm($booking, 'stripe');

    $tests->near(80.0, $total, 'cumul des remises');
    $tests->same('confirmed', $booking->status, 'réservation confirmée');
});

$tests->run('plusieurs billets', function () use ($tests, $service) {
    $booking = createBooking(price: 25.0, quantity: 2);
    $booking->addItem(new BookingItem(new Ticket('OTHER', 'Autre billet', 30.0), 3));
    $tests->near(140.0, $service->confirm($booking), 'somme des lignes de réservation');
});

$tests->run('confirmation répétée actuelle', function () use ($tests, $service) {
    $booking = createBooking();
    $service->confirm($booking);
    $tests->near(50.0, $service->confirm($booking), 'seconde confirmation encore autorisée');
    $tests->same('confirmed', $booking->status, 'statut après seconde confirmation');
});

$empty = createBooking();
$empty->items = [];
$invalidEmail = createBooking();
$invalidEmail->customer->email = 'adresse-invalide';

$errorCases = [
    ['réservation vide', $empty, 'stripe', 'Empty booking'],
    ['email invalide', $invalidEmail, 'stripe', 'Invalid email'],
    ['quantité nulle', createBooking(quantity: 0), 'stripe', 'Invalid quantity'],
    ['quantité négative', createBooking(quantity: -1), 'stripe', 'Invalid quantity'],
    ['prix nul', createBooking(price: 0.0), 'stripe', 'Invalid amount'],
    ['prix négatif', createBooking(price: -5.0), 'stripe', 'Invalid amount'],
    ['total nul après remise', createBooking(passType: '3days', price: 10.0), 'stripe', 'Invalid amount'],
    ['total négatif après remise', createBooking(passType: '3days', price: 5.0), 'stripe', 'Invalid amount'],
    ['PayFast indisponible', createBooking(), 'payfast', 'PayFast not implemented'],
    ['paiement inconnu', createBooking(), 'unknown', 'Unknown payment method'],
];

foreach ($errorCases as [$label, $booking, $paymentMethod, $message]) {
    $tests->run($label, function () use ($tests, $service, $label, $booking, $paymentMethod, $message) {
        $tests->throws(
            fn () => $service->confirm($booking, $paymentMethod),
            RuntimeException::class,
            $message,
            $label
        );
        $tests->same('pending', $booking->status, $label . ' : statut conservé');
    });
}

$tests->summary();
