<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$categories = get_terms( [ 'taxonomy' => 'faq_category', 'hide_empty' => false, 'orderby' => 'name' ] );
if ( is_wp_error( $categories ) ) $categories = [];

$all_faqs        = [];
$total_faq_count = 0;
foreach ( $categories as $cat ) {
    $faqs                      = get_posts( [
        'post_type'      => 'bp_faq',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'tax_query'      => [ [ 'taxonomy' => 'faq_category', 'field' => 'term_id', 'terms' => $cat->term_id ] ],
    ] );
    $all_faqs[ $cat->term_id ] = $faqs;
    $total_faq_count          += count( $faqs );
}

$nonce     = wp_create_nonce( 'bp_faq_nonce' );
$cat_count = count( $categories );
?>
<style>
/* ============================================================
   BabyPasa FAQ — Admin Page Styles
   ============================================================ */
.bp-faq-admin { max-width: 920px; padding-bottom: 60px; }

/* Header */
.bp-faq-admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dcdcde;
}
.bp-faq-admin-header h1 { margin: 0; padding: 0; line-height: 1.3; }
.bp-faq-admin-header .bp-header-right { display: flex; align-items: center; gap: 10px; }
.bp-stats { font-size: 13px; color: #646970; }

/* Add-category inline form */
.bp-add-cat-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.bp-add-cat-form label { font-weight: 600; font-size: 13px; white-space: nowrap; }
.bp-add-cat-form input[type="text"] { flex: 1; min-width: 200px; }

/* Category sections */
.bp-admin-section {
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    transition: box-shadow .2s;
}
.bp-admin-section:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }

/* Category header — dark, matching frontend */
.bp-admin-cat-header {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #1f1f1f;
    padding: 0 14px 0 10px;
    min-height: 50px;
    flex-wrap: wrap;
}
.bp-cat-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 4px;
    color: rgba(255,255,255,0.7);
    flex-shrink: 0;
    line-height: 1;
    border-radius: 3px;
    transition: color .15s, background .15s;
}
.bp-cat-toggle:hover { color: #fff; background: rgba(255,255,255,0.1); }
.bp-cat-toggle .dashicons { font-size: 16px; width: 16px; height: 16px; transition: transform .25s; }
.bp-admin-section.is-collapsed .bp-cat-toggle .dashicons { transform: rotate(-90deg); }

.bp-cat-name {
    flex: 1;
    font-size: 12.5px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.7px;
    text-transform: uppercase;
    min-width: 0;
}
.bp-cat-name-input {
    flex: 1;
    min-width: 140px;
    font-size: 12.5px;
    font-weight: 700;
    letter-spacing: 0.6px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.35);
    color: #fff;
    padding: 5px 10px;
    border-radius: 3px;
    height: 30px;
}
.bp-cat-name-input::placeholder { color: rgba(255,255,255,0.4); font-weight: 400; letter-spacing: 0; }
.bp-cat-name-input:focus { outline: none; background: rgba(255,255,255,0.16); border-color: rgba(255,255,255,0.6); }

.bp-cat-count {
    font-size: 11px;
    color: rgba(255,255,255,0.45);
    flex-shrink: 0;
    margin: 0 2px;
}

/* Category action buttons */
.bp-cat-actions { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.bp-cat-actions .button {
    font-size: 12px;
    height: 28px;
    line-height: 26px;
    padding: 0 9px;
    border-radius: 3px;
}
.bp-btn-add-faq {
    background: #FF2A61 !important;
    border-color: #c41f4b !important;
    color: #fff !important;
    font-weight: 600 !important;
}
.bp-btn-add-faq:hover {
    background: #d41f4e !important;
    border-color: #a8183d !important;
    color: #fff !important;
}
.bp-btn-edit-cat, .bp-btn-save-cat, .bp-btn-cancel-cat {
    background: rgba(255,255,255,0.1) !important;
    border-color: rgba(255,255,255,0.2) !important;
    color: #e0e0e0 !important;
    padding: 0 7px !important;
}
.bp-btn-edit-cat:hover, .bp-btn-save-cat:hover {
    background: rgba(255,255,255,0.22) !important;
    border-color: rgba(255,255,255,0.45) !important;
    color: #fff !important;
}
.bp-btn-cancel-cat:hover {
    background: rgba(220,50,50,0.3) !important;
    border-color: rgba(220,50,50,0.5) !important;
    color: #ffa0a0 !important;
}
.bp-btn-delete-cat {
    background: rgba(220,38,38,0.12) !important;
    border-color: rgba(220,38,38,0.25) !important;
    color: #f87171 !important;
    padding: 0 7px !important;
}
.bp-btn-delete-cat:hover {
    background: rgba(220,38,38,0.32) !important;
    border-color: rgba(220,38,38,0.55) !important;
    color: #fff !important;
}
.bp-cat-actions .dashicons { font-size: 15px; width: 15px; height: 15px; vertical-align: middle; }

/* FAQ list */
.bp-admin-faqs { list-style: none; margin: 0; padding: 0; }
.bp-admin-section.is-collapsed .bp-admin-faqs { display: none; }

.bp-no-faqs {
    padding: 18px 20px;
    color: #9ca3af;
    font-size: 13px;
    font-style: italic;
    text-align: center;
    border-top: 1px solid #f3f3f3;
}
.bp-no-faqs strong { color: #6b7280; font-style: normal; }

/* FAQ item row */
.bp-admin-faq-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border-top: 1px solid #f0f0f0;
    transition: background .12s;
    position: relative;
}
.bp-admin-faq-item:first-child { border-top-color: #e8e8e8; }
.bp-admin-faq-item:hover { background: #fafbfc; }
.bp-admin-faq-item.ui-sortable-helper {
    box-shadow: 0 4px 16px rgba(0,0,0,0.14);
    background: #fff;
    border-radius: 4px;
    border: 1px solid #dde0e3;
    z-index: 9999;
}

.bp-drag-handle {
    cursor: grab;
    color: #c8c8c8;
    flex-shrink: 0;
    font-size: 18px !important;
    width: 18px !important;
    height: 18px !important;
    opacity: 0;
    transition: opacity .15s;
}
.bp-admin-faq-item:hover .bp-drag-handle { opacity: 1; }
.bp-drag-handle:active { cursor: grabbing; }

.bp-faq-q-number {
    font-size: 11px;
    color: #bbb;
    font-weight: 600;
    flex-shrink: 0;
    min-width: 18px;
    text-align: right;
}
.bp-faq-q-text {
    flex: 1;
    font-size: 13.5px;
    color: #1d2327;
    font-weight: 500;
    line-height: 1.4;
}

.bp-faq-item-actions {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity .15s;
}
.bp-admin-faq-item:hover .bp-faq-item-actions { opacity: 1; }

.bp-btn-edit-faq {
    background: #f0f6fc !important;
    border-color: #b4d0e9 !important;
    color: #2271b1 !important;
    font-size: 12px !important;
    height: 26px !important;
    line-height: 24px !important;
    padding: 0 9px !important;
}
.bp-btn-edit-faq:hover {
    background: #2271b1 !important;
    border-color: #135e96 !important;
    color: #fff !important;
}
.bp-btn-delete-faq {
    background: #fdf2f2 !important;
    border-color: #ebbebe !important;
    color: #b91c1c !important;
    font-size: 12px !important;
    height: 26px !important;
    line-height: 24px !important;
    padding: 0 9px !important;
}
.bp-btn-delete-faq:hover {
    background: #b91c1c !important;
    border-color: #991b1b !important;
    color: #fff !important;
}

.bp-sortable-placeholder {
    background: #f0f6fc;
    border: 2px dashed #93c5e8;
    height: 44px;
    border-radius: 2px;
    display: block;
}

/* Empty state */
.bp-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px dashed #d0d5da;
    border-radius: 6px;
    color: #646970;
}
.bp-empty-state p { font-size: 15px; margin-bottom: 16px; }

/* ============================================================
   Modal
   ============================================================ */
.bp-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.bp-modal {
    background: #fff;
    border-radius: 8px;
    width: 620px;
    max-width: calc(100vw - 48px);
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 64px rgba(0,0,0,0.28);
    overflow: hidden;
}
.bp-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 22px;
    background: #1f1f1f;
    flex-shrink: 0;
}
.bp-modal-header h2 { margin: 0; font-size: 15px; color: #fff; font-weight: 700; letter-spacing: 0.4px; }
.bp-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    padding: 2px 6px;
    border-radius: 4px;
    transition: color .15s, background .15s;
}
.bp-modal-close:hover { color: #fff; background: rgba(255,255,255,0.12); }

.bp-modal-body { padding: 22px 22px 18px; overflow-y: auto; flex: 1; }

.bp-form-group { margin-bottom: 18px; }
.bp-form-group:last-child { margin-bottom: 0; }
.bp-form-group > label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #1d2327;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.bp-form-group > label small {
    font-weight: 400;
    color: #787c82;
    font-size: 11.5px;
    text-transform: none;
    letter-spacing: 0;
    margin-left: 4px;
}
.bp-form-group .large-text { width: 100%; box-sizing: border-box; }
.bp-form-group textarea.large-text {
    resize: vertical;
    font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
    font-size: 12.5px;
    line-height: 1.6;
    min-height: 130px;
    background: #f9fafb;
    border-color: #d0d5da;
    color: #1d2327;
}
.bp-form-group textarea.large-text:focus { background: #fff; border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }

.bp-modal-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 22px;
    border-top: 1px solid #e8e8e8;
    background: #f8f9fa;
    flex-shrink: 0;
}
.bp-modal-footer .spinner { float: none; margin: 0 4px; visibility: hidden; }
.bp-modal-footer .spinner.is-active { visibility: visible; }

/* ============================================================
   Toast notification
   ============================================================ */
.bp-toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: #1d2327;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    padding: 10px 18px;
    border-radius: 5px;
    z-index: 200000;
    box-shadow: 0 4px 16px rgba(0,0,0,0.22);
    opacity: 0;
    transform: translateY(8px);
    transition: opacity .25s, transform .25s;
    pointer-events: none;
    max-width: 320px;
}
.bp-toast.show { opacity: 1; transform: translateY(0); }
.bp-toast.is-error { background: #b91c1c; }
.bp-toast.is-ok   { background: #15803d; }
</style>

<?php
$faq_label = $total_faq_count . ' FAQ' . ( $total_faq_count !== 1 ? 's' : '' );
$cat_label = $cat_count . ' categor' . ( $cat_count !== 1 ? 'ies' : 'y' );
?>

<div class="wrap bp-faq-admin">

    <!-- Page Header -->
    <div class="bp-faq-admin-header">
        <h1 class="wp-heading-inline">BabyPasa FAQs</h1>
        <div class="bp-header-right">
            <span class="bp-stats" id="bp-stats"><?php echo esc_html( "$faq_label in $cat_label" ); ?></span>
            <button class="button button-primary" id="bp-add-cat-btn">&#43; Add Category</button>
        </div>
    </div>

    <!-- Add Category Form -->
    <div class="bp-add-cat-form" id="bp-add-cat-form" style="display:none;">
        <label for="bp-new-cat-name">Category name:</label>
        <input type="text" id="bp-new-cat-name" class="regular-text" placeholder="e.g. Shipping &amp; Delivery" autocomplete="off">
        <button class="button button-primary" id="bp-save-new-cat">Save Category</button>
        <button class="button" id="bp-cancel-new-cat">Cancel</button>
    </div>

    <!-- Categories List -->
    <div id="bp-categories-list">
        <?php if ( empty( $categories ) ) : ?>
            <div class="bp-empty-state" id="bp-empty-state">
                <p>No FAQ categories yet.<br>Click <strong>"+ Add Category"</strong> to get started.</p>
            </div>
        <?php else : ?>
            <?php foreach ( $categories as $cat ) :
                $faqs  = $all_faqs[ $cat->term_id ] ?? [];
                $count = count( $faqs );
            ?>
            <div class="bp-admin-section" data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>">

                <div class="bp-admin-cat-header">
                    <button class="bp-cat-toggle" title="Expand / collapse">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <span class="bp-cat-name"><?php echo esc_html( strtoupper( $cat->name ) ); ?></span>
                    <input type="text" class="bp-cat-name-input" style="display:none;"
                           value="<?php echo esc_attr( $cat->name ); ?>" placeholder="Category name">
                    <span class="bp-cat-count"><?php echo $count; ?> FAQ<?php echo $count !== 1 ? 's' : ''; ?></span>
                    <div class="bp-cat-actions">
                        <button class="button bp-btn-add-faq" data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>">&#43; Add FAQ</button>
                        <button class="button bp-btn-edit-cat" title="Rename category"><span class="dashicons dashicons-edit"></span></button>
                        <button class="button bp-btn-save-cat" title="Save" style="display:none;"><span class="dashicons dashicons-yes-alt"></span></button>
                        <button class="button bp-btn-cancel-cat" title="Cancel" style="display:none;"><span class="dashicons dashicons-dismiss"></span></button>
                        <button class="button bp-btn-delete-cat" title="Delete category and all its FAQs"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                </div>

                <div class="bp-admin-faqs" id="faq-list-<?php echo esc_attr( $cat->term_id ); ?>">
                    <?php if ( empty( $faqs ) ) : ?>
                        <div class="bp-no-faqs">No FAQs yet — click <strong>+ Add FAQ</strong> above to add one.</div>
                    <?php else : ?>
                        <?php foreach ( $faqs as $i => $faq ) : ?>
                        <div class="bp-admin-faq-item"
                             data-faq-id="<?php echo esc_attr( $faq->ID ); ?>"
                             data-question="<?php echo esc_attr( $faq->post_title ); ?>"
                             data-answer="<?php echo esc_attr( $faq->post_content ); ?>">
                            <span class="dashicons dashicons-menu bp-drag-handle" title="Drag to reorder"></span>
                            <span class="bp-faq-q-number"><?php echo $i + 1; ?></span>
                            <span class="bp-faq-q-text"><?php echo esc_html( $faq->post_title ); ?></span>
                            <div class="bp-faq-item-actions">
                                <button class="button button-small bp-btn-edit-faq">Edit</button>
                                <button class="button button-small bp-btn-delete-faq">Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- .wrap -->

<!-- ============================================================
     FAQ Modal
     ============================================================ -->
<div id="bp-faq-modal" class="bp-modal-overlay" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="bp-modal-title">
    <div class="bp-modal">
        <div class="bp-modal-header">
            <h2 id="bp-modal-title">Add FAQ</h2>
            <button class="bp-modal-close" id="bp-modal-close" title="Close">&times;</button>
        </div>
        <div class="bp-modal-body">
            <input type="hidden" id="bp-modal-faq-id" value="0">
            <input type="hidden" id="bp-modal-cat-id" value="0">
            <div class="bp-form-group">
                <label for="bp-modal-question">Question</label>
                <input type="text" id="bp-modal-question" class="large-text"
                       placeholder="What question are customers asking?">
            </div>
            <div class="bp-form-group">
                <label for="bp-modal-answer">
                    Answer
                    <small>HTML supported — &lt;ul&gt;&lt;li&gt; for lists, &lt;strong&gt; for bold</small>
                </label>
                <textarea id="bp-modal-answer" class="large-text" rows="7"
                          placeholder="Type the answer here..."></textarea>
            </div>
        </div>
        <div class="bp-modal-footer">
            <button class="button button-primary" id="bp-modal-save">Save FAQ</button>
            <button class="button" id="bp-modal-cancel">Cancel</button>
            <span class="spinner bp-modal-spinner"></span>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="bp-toast" class="bp-toast" role="status" aria-live="polite"></div>

<script>
jQuery(function ($) {
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
    var AJAX  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    /* ----------------------------------------------------------
       Helpers
    ---------------------------------------------------------- */

    var toastTimer;
    function toast(msg, type) {
        var $t = $('#bp-toast').text(msg)
            .removeClass('is-error is-ok')
            .addClass(type === 'error' ? 'is-error' : 'is-ok')
            .addClass('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { $t.removeClass('show'); }, 2800);
    }

    function updateStats() {
        var faqs = $('.bp-admin-faq-item').length;
        var cats = $('.bp-admin-section').length;
        $('#bp-stats').text(
            faqs + ' FAQ' + (faqs !== 1 ? 's' : '') + ' in ' +
            cats + ' categor' + (cats !== 1 ? 'ies' : 'y')
        );
    }

    function refreshNumbers($section) {
        $section.find('.bp-admin-faq-item').each(function (i) {
            $(this).find('.bp-faq-q-number').text(i + 1);
        });
        var count = $section.find('.bp-admin-faq-item').length;
        $section.find('.bp-cat-count').text(count + ' FAQ' + (count !== 1 ? 's' : ''));
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ----------------------------------------------------------
       Sortable (drag-to-reorder within a category)
    ---------------------------------------------------------- */

    function initSortable($list) {
        $list.sortable({
            handle:      '.bp-drag-handle',
            axis:        'y',
            tolerance:   'pointer',
            placeholder: 'bp-sortable-placeholder',
            start: function (e, ui) { ui.placeholder.height(ui.item.outerHeight()); },
            update: function () {
                var order = $list.find('.bp-admin-faq-item').map(function () {
                    return $(this).data('faq-id');
                }).get();
                $.post(AJAX, { action: 'bp_faq_reorder', nonce: NONCE, order: order });
                refreshNumbers($list.closest('.bp-admin-section'));
            }
        });
    }

    $('.bp-admin-faqs').each(function () { initSortable($(this)); });

    /* ----------------------------------------------------------
       Toggle category collapse
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-cat-toggle', function () {
        $(this).closest('.bp-admin-section').toggleClass('is-collapsed');
    });

    /* ----------------------------------------------------------
       Add Category
    ---------------------------------------------------------- */

    $('#bp-add-cat-btn').on('click', function () {
        var $form = $('#bp-add-cat-form');
        $form.slideToggle(180);
        if ($form.is(':hidden')) return;
        setTimeout(function () { $('#bp-new-cat-name').focus(); }, 190);
    });

    $('#bp-cancel-new-cat').on('click', function () {
        $('#bp-add-cat-form').slideUp(180);
        $('#bp-new-cat-name').val('');
    });

    function doSaveNewCat() {
        var name = $('#bp-new-cat-name').val().trim();
        if (!name) { toast('Please enter a category name.', 'error'); return; }

        var $btn = $('#bp-save-new-cat').prop('disabled', true).text('Saving…');
        $.post(AJAX, { action: 'bp_faq_save_category', nonce: NONCE, name: name })
            .done(function (res) {
                if (!res.success) { toast(res.data.message || 'Error saving category.', 'error'); return; }
                var $new = $(buildSectionHtml(res.data.id, name));
                $('#bp-empty-state').remove();
                $('#bp-categories-list').append($new);
                initSortable($new.find('.bp-admin-faqs'));
                $('#bp-new-cat-name').val('');
                $('#bp-add-cat-form').slideUp(180);
                updateStats();
                toast('"' + name + '" category created.');
            })
            .fail(function () { toast('Server error.', 'error'); })
            .always(function () { $btn.prop('disabled', false).text('Save Category'); });
    }

    $('#bp-save-new-cat').on('click', doSaveNewCat);
    $('#bp-new-cat-name').on('keydown', function (e) {
        if (e.key === 'Enter')  doSaveNewCat();
        if (e.key === 'Escape') $('#bp-cancel-new-cat').click();
    });

    /* ----------------------------------------------------------
       Edit Category (inline rename)
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-btn-edit-cat', function () {
        var $h = $(this).closest('.bp-admin-section').find('.bp-admin-cat-header');
        $h.find('.bp-cat-name').hide();
        $h.find('.bp-cat-name-input').show().focus().select();
        $h.find('.bp-btn-edit-cat').hide();
        $h.find('.bp-btn-save-cat, .bp-btn-cancel-cat').show();
    });

    $(document).on('click', '.bp-btn-cancel-cat', function () {
        var $section = $(this).closest('.bp-admin-section');
        var $h = $section.find('.bp-admin-cat-header');
        $h.find('.bp-cat-name-input').hide();
        $h.find('.bp-cat-name').show();
        $h.find('.bp-btn-edit-cat').show();
        $h.find('.bp-btn-save-cat, .bp-btn-cancel-cat').hide();
    });

    $(document).on('click', '.bp-btn-save-cat', function () {
        var $section = $(this).closest('.bp-admin-section');
        var $h       = $section.find('.bp-admin-cat-header');
        var catId    = $section.data('cat-id');
        var name     = $h.find('.bp-cat-name-input').val().trim();
        if (!name) { toast('Name cannot be empty.', 'error'); return; }

        var $btn = $(this).prop('disabled', true);
        $.post(AJAX, { action: 'bp_faq_save_category', nonce: NONCE, cat_id: catId, name: name })
            .done(function (res) {
                if (!res.success) { toast(res.data.message || 'Error.', 'error'); return; }
                $h.find('.bp-cat-name').text(name.toUpperCase()).show();
                $h.find('.bp-cat-name-input').hide();
                $h.find('.bp-btn-edit-cat').show();
                $h.find('.bp-btn-save-cat, .bp-btn-cancel-cat').hide();
                toast('Category renamed.');
            })
            .fail(function () { toast('Server error.', 'error'); })
            .always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('keydown', '.bp-cat-name-input', function (e) {
        if (e.key === 'Enter')  $(this).closest('.bp-admin-section').find('.bp-btn-save-cat').click();
        if (e.key === 'Escape') $(this).closest('.bp-admin-section').find('.bp-btn-cancel-cat').click();
    });

    /* ----------------------------------------------------------
       Delete Category
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-btn-delete-cat', function () {
        var $section = $(this).closest('.bp-admin-section');
        var name     = $section.find('.bp-cat-name').text();
        var count    = $section.find('.bp-admin-faq-item').length;
        var msg      = 'Delete the category "' + name + '"?';
        if (count > 0) msg += '\n\nThis will permanently delete all ' + count + ' FAQ' + (count !== 1 ? 's' : '') + ' inside it.';

        if (!confirm(msg)) return;

        var catId = $section.data('cat-id');
        $.post(AJAX, { action: 'bp_faq_delete_category', nonce: NONCE, cat_id: catId })
            .done(function (res) {
                if (!res.success) { toast('Error deleting category.', 'error'); return; }
                $section.fadeOut(250, function () {
                    $(this).remove();
                    if (!$('.bp-admin-section').length) {
                        $('#bp-categories-list').html(
                            '<div class="bp-empty-state" id="bp-empty-state"><p>No FAQ categories yet.<br>' +
                            'Click <strong>"+ Add Category"</strong> to get started.</p></div>'
                        );
                    }
                    updateStats();
                });
                toast('Category deleted.');
            })
            .fail(function () { toast('Server error.', 'error'); });
    });

    /* ----------------------------------------------------------
       FAQ Modal — open / close
    ---------------------------------------------------------- */

    function openModal(title, faqId, catId, question, answer) {
        $('#bp-modal-title').text(title);
        $('#bp-modal-faq-id').val(faqId || 0);
        $('#bp-modal-cat-id').val(catId || 0);
        $('#bp-modal-question').val(question || '');
        $('#bp-modal-answer').val(answer || '');
        $('#bp-modal-save').prop('disabled', false).text('Save FAQ');
        $('.bp-modal-spinner').removeClass('is-active');
        $('#bp-faq-modal').fadeIn(180);
        setTimeout(function () { $('#bp-modal-question').focus(); }, 190);
    }

    function closeModal() { $('#bp-faq-modal').fadeOut(150); }

    $('#bp-modal-close, #bp-modal-cancel').on('click', closeModal);
    $('#bp-faq-modal').on('click', function (e) { if ($(e.target).is(this)) closeModal(); });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#bp-faq-modal').is(':visible')) closeModal();
    });

    /* ----------------------------------------------------------
       Add FAQ
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-btn-add-faq', function () {
        openModal('Add FAQ', 0, $(this).data('cat-id'), '', '');
    });

    /* ----------------------------------------------------------
       Edit FAQ
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-btn-edit-faq', function () {
        var $item = $(this).closest('.bp-admin-faq-item');
        openModal(
            'Edit FAQ',
            $item.data('faq-id'),
            $item.closest('.bp-admin-section').data('cat-id'),
            $item.data('question'),
            $item.data('answer')
        );
    });

    /* ----------------------------------------------------------
       Save FAQ (modal submit)
    ---------------------------------------------------------- */

    $('#bp-modal-save').on('click', function () {
        var question = $('#bp-modal-question').val().trim();
        var answer   = $('#bp-modal-answer').val().trim();
        var faqId    = parseInt($('#bp-modal-faq-id').val(), 10) || 0;
        var catId    = parseInt($('#bp-modal-cat-id').val(), 10) || 0;

        if (!question) {
            toast('Please enter a question.', 'error');
            $('#bp-modal-question').focus();
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Saving…');
        $('.bp-modal-spinner').addClass('is-active');

        $.post(AJAX, {
            action:   'bp_faq_save_faq',
            nonce:    NONCE,
            faq_id:   faqId,
            cat_id:   catId,
            question: question,
            answer:   answer,
        })
        .done(function (res) {
            if (!res.success) { toast(res.data.message || 'Error saving FAQ.', 'error'); return; }

            if (faqId) {
                var $item = $('[data-faq-id="' + faqId + '"]');
                $item.find('.bp-faq-q-text').text(question);
                $item.data({ question: question, answer: answer })
                     .attr({ 'data-question': question, 'data-answer': answer });
                toast('FAQ updated.');
            } else {
                var $list = $('#faq-list-' + catId);
                $list.find('.bp-no-faqs').remove();
                var $new  = $(buildFaqItemHtml(res.data.id, question, answer));
                $list.append($new);
                refreshNumbers($list.closest('.bp-admin-section'));
                updateStats();
                toast('FAQ added.');
            }
            closeModal();
        })
        .fail(function () { toast('Server error.', 'error'); })
        .always(function () {
            $btn.prop('disabled', false).text('Save FAQ');
            $('.bp-modal-spinner').removeClass('is-active');
        });
    });

    /* ----------------------------------------------------------
       Delete FAQ
    ---------------------------------------------------------- */

    $(document).on('click', '.bp-btn-delete-faq', function () {
        var $item    = $(this).closest('.bp-admin-faq-item');
        var question = $item.data('question');
        if (!confirm('Delete this FAQ?\n\n"' + question + '"')) return;

        var faqId = $item.data('faq-id');
        $.post(AJAX, { action: 'bp_faq_delete_faq', nonce: NONCE, faq_id: faqId })
            .done(function (res) {
                if (!res.success) { toast('Error deleting FAQ.', 'error'); return; }
                var $section = $item.closest('.bp-admin-section');
                $item.fadeOut(200, function () {
                    $(this).remove();
                    if (!$section.find('.bp-admin-faq-item').length) {
                        $section.find('.bp-admin-faqs').html(
                            '<div class="bp-no-faqs">No FAQs yet — click <strong>+ Add FAQ</strong> above to add one.</div>'
                        );
                    }
                    refreshNumbers($section);
                    updateStats();
                });
                toast('FAQ deleted.');
            })
            .fail(function () { toast('Server error.', 'error'); });
    });

    /* ----------------------------------------------------------
       DOM builders (used when inserting new items via JS)
    ---------------------------------------------------------- */

    function buildFaqItemHtml(id, question, answer) {
        return '<div class="bp-admin-faq-item"' +
            ' data-faq-id="' + id + '"' +
            ' data-question="' + escAttr(question) + '"' +
            ' data-answer="' + escAttr(answer) + '">' +
            '<span class="dashicons dashicons-menu bp-drag-handle" title="Drag to reorder"></span>' +
            '<span class="bp-faq-q-number"></span>' +
            '<span class="bp-faq-q-text">' + escHtml(question) + '</span>' +
            '<div class="bp-faq-item-actions">' +
            '<button class="button button-small bp-btn-edit-faq">Edit</button>' +
            '<button class="button button-small bp-btn-delete-faq">Delete</button>' +
            '</div></div>';
    }

    function buildSectionHtml(catId, name) {
        return '<div class="bp-admin-section" data-cat-id="' + catId + '">' +
            '<div class="bp-admin-cat-header">' +
            '<button class="bp-cat-toggle" title="Expand / collapse">' +
            '<span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
            '<span class="bp-cat-name">' + escHtml(name.toUpperCase()) + '</span>' +
            '<input type="text" class="bp-cat-name-input" style="display:none;"' +
            ' value="' + escAttr(name) + '" placeholder="Category name">' +
            '<span class="bp-cat-count">0 FAQs</span>' +
            '<div class="bp-cat-actions">' +
            '<button class="button bp-btn-add-faq" data-cat-id="' + catId + '">&#43; Add FAQ</button>' +
            '<button class="button bp-btn-edit-cat" title="Rename">' +
            '<span class="dashicons dashicons-edit"></span></button>' +
            '<button class="button bp-btn-save-cat" title="Save" style="display:none;">' +
            '<span class="dashicons dashicons-yes-alt"></span></button>' +
            '<button class="button bp-btn-cancel-cat" title="Cancel" style="display:none;">' +
            '<span class="dashicons dashicons-dismiss"></span></button>' +
            '<button class="button bp-btn-delete-cat" title="Delete category">' +
            '<span class="dashicons dashicons-trash"></span></button>' +
            '</div></div>' +
            '<div class="bp-admin-faqs" id="faq-list-' + catId + '">' +
            '<div class="bp-no-faqs">No FAQs yet — click <strong>+ Add FAQ</strong> above to add one.</div>' +
            '</div></div>';
    }

});
</script>
