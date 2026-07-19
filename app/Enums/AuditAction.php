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
    const LOT_ASSIGNED  = 'lot.assigned';
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
    const CONSIGNMENT_DELETED           = 'consignment.deleted';
    const CONSIGNMENT_CONFIRMED         = 'consignment.confirmed';
    const CONSIGNMENT_ITEM_ADDED        = 'consignment.item_added';
    const CONSIGNMENT_ITEM_UPDATED      = 'consignment.item_updated';
    const CONSIGNMENT_ITEM_REMOVED      = 'consignment.item_removed';
    const CONSIGNMENT_POST_CONFIRM_EDIT = 'consignment.post_confirm_edit';

    // -------------------------------------------------------------------------
    // Return Session
    // -------------------------------------------------------------------------
    const RETURN_SESSION_CREATED   = 'return_session.created';
    const RETURN_SESSION_ITEM_SCANNED = 'return_session.item_scanned';
    const RETURN_SESSION_ITEM_REMOVED = 'return_session.item_removed';
    const RETURN_SESSION_COMPLETED = 'return_session.completed';
    const RETURN_SESSION_REOPENED = 'return_session.reopened';

    // -------------------------------------------------------------------------
    // Reconciliation
    // -------------------------------------------------------------------------
    const RECONCILIATION_CREATED   = 'reconciliation.created';
    const RECONCILIATION_FINALIZED = 'reconciliation.finalized';
    const RECONCILIATION_REOPENED  = 'reconciliation.reopened';
    const INSTRUMENT_SET_UPDATED   = 'instrument_set.updated';
    const INSTRUMENT_SET_DELETED   = 'instrument_set.deleted';

    // -------------------------------------------------------------------------
    // Disposal
    // -------------------------------------------------------------------------
    const DISPOSAL_CREATED      = 'disposal.created';
    const DISPOSAL_UPDATED      = 'disposal.updated';
    const DISPOSAL_ITEM_ADDED   = 'disposal.item_added';
    const DISPOSAL_ITEM_REMOVED = 'disposal.item_removed';
    const DISPOSAL_COMPLETED    = 'disposal.completed';

    // -------------------------------------------------------------------------
    // Supplier Return
    // -------------------------------------------------------------------------
    const SUPPLIER_RETURN_CREATED      = 'supplier_return.created';
    const SUPPLIER_RETURN_UPDATED      = 'supplier_return.updated';
    const SUPPLIER_RETURN_ITEM_ADDED   = 'supplier_return.item_added';
    const SUPPLIER_RETURN_ITEM_REMOVED = 'supplier_return.item_removed';
    const SUPPLIER_RETURN_COMPLETED    = 'supplier_return.completed';
    const SUPPLIER_RETURN_DELETED      = 'supplier_return.deleted';

}
