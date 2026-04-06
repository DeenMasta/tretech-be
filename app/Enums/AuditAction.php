<?php

namespace App\Enums;

class AuditAction
{
    // -------------------------------------------------------------------------
    // Stock-In
    // -------------------------------------------------------------------------
    const STOCK_IN_CREATED    = 'stock_in.created';
    const STOCK_IN_UPDATED    = 'stock_in.updated';
    const STOCK_IN_FINALIZED  = 'stock_in.finalized';
    const STOCK_IN_ITEM_ADDED = 'stock_in.item_added';
    const STOCK_IN_ITEM_UPDATED = 'stock_in.item_updated';
    const STOCK_IN_ITEM_DELETED = 'stock_in.item_deleted';

    // -------------------------------------------------------------------------
    // Inventory / Lot
    // -------------------------------------------------------------------------
    const LOT_CREATED   = 'lot.created';
    const LOT_UPDATED   = 'lot.updated';
    const LOT_MOVED     = 'lot.moved';
    const LOT_HELD      = 'lot.held';
    const LOT_RELEASED  = 'lot.released';
    const LOT_DISPOSED  = 'lot.disposed';

    // -------------------------------------------------------------------------
    // QR Labels & Print Jobs
    // -------------------------------------------------------------------------
    const QR_LABEL_CREATED      = 'qr_label.created';
    const PRINT_JOB_CREATED     = 'print_job.created';
    const PRINT_JOB_REPRINTED   = 'print_job.reprinted';
    const PRINT_JOB_MARKED_PRINTED = 'print_job.marked_printed';
    const PRINT_JOB_MARKED_FAILED  = 'print_job.marked_failed';

    // -------------------------------------------------------------------------
    // Users & Auth
    // -------------------------------------------------------------------------
    const USER_CREATED    = 'user.created';
    const USER_UPDATED    = 'user.updated';
    const USER_DELETED    = 'user.deleted';
    const USER_LOGGED_IN  = 'user.logged_in';
    const USER_LOGGED_OUT = 'user.logged_out';

    // -------------------------------------------------------------------------
    // Master Data
    // -------------------------------------------------------------------------
    const PRODUCT_CREATED  = 'product.created';
    const PRODUCT_UPDATED  = 'product.updated';
    const PRODUCT_DELETED  = 'product.deleted';

    const SUPPLIER_CREATED = 'supplier.created';
    const SUPPLIER_UPDATED = 'supplier.updated';
    const SUPPLIER_DELETED = 'supplier.deleted';

    const CLIENT_CREATED   = 'client.created';
    const CLIENT_UPDATED   = 'client.updated';
    const CLIENT_DELETED   = 'client.deleted';

    const INSTRUMENT_SET_CREATED = 'instrument_set.created';

    // -------------------------------------------------------------------------
    // Consignment
    // -------------------------------------------------------------------------
    const CONSIGNMENT_CREATED           = 'consignment.created';
    const CONSIGNMENT_UPDATED           = 'consignment.updated';
    const CONSIGNMENT_CONFIRMED         = 'consignment.confirmed';
    const CONSIGNMENT_ITEM_ADDED        = 'consignment.item_added';
    const CONSIGNMENT_ITEM_REMOVED      = 'consignment.item_removed';
    const CONSIGNMENT_POST_CONFIRM_EDIT = 'consignment.post_confirm_edit';
    const INSTRUMENT_SET_UPDATED = 'instrument_set.updated';
    const INSTRUMENT_SET_DELETED = 'instrument_set.deleted';
}
