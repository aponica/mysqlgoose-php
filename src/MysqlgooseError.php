<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

namespace Aponica\Mysqlgoose;

//-----------------------------------------------------------------------------
/// Error class for Mysqlgoose errors.
///
/// Note that errors raised by dependencies (such as `mysqli`) will *not*
/// be instances of this class.
///
/// @extends Error
//-----------------------------------------------------------------------------

class MysqlgooseError extends \Exception {
}

// EOF
