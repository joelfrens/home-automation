<?php

namespace Joelfrens\HomeAutomation\Sensors\Exceptions;

use InvalidArgumentException;

/** An MQTT message that is not a valid sensor reading; the message says why. */
class InvalidSensorMessage extends InvalidArgumentException {}
