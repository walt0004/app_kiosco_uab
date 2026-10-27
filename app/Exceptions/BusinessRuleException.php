<?php

namespace App\Exceptions;

use DomainException;

/**
 * Error de regla de negocio con un mensaje pensado para mostrarse al usuario
 * (turno de caja cerrado, venta ya anulada, producto inactivo, etc.).
 */
class BusinessRuleException extends DomainException {}
