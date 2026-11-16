<?php $t = function ($p) { return base_url('theme/assets/' . $p) . '?v=' . @filemtime(FCPATH . 'theme/assets/' . $p); }; ?>
<script src="<?= $t('vendor/bootstrap/js/bootstrap.js') ?>"></script>
<script src="<?= $t('vendor/nanoscroller/nanoscroller.js') ?>"></script>
<!-- DataTables, as in the app: search, export buttons, paging -->
<script src="<?= $t('vendor/datatables/media/js/jquery.dataTables.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/media/js/dataTables.bootstrap.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/Buttons-1.4.2/js/dataTables.buttons.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/Buttons-1.4.2/js/buttons.bootstrap.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/Buttons-1.4.2/js/buttons.html5.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/Buttons-1.4.2/js/buttons.print.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/Buttons-1.4.2/js/buttons.colVis.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/JSZip-2.5.0/jszip.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/pdfmake-0.1.32/pdfmake.min.js') ?>"></script>
<script src="<?= $t('vendor/datatables/extras/TableTools/pdfmake-0.1.32/vfs_fonts.js') ?>"></script>
<script src="<?= $t('vendor/sweetalert/sweetalert.min.js') ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>var derzText = { sortBy: 'Sort by', moreDetails: 'More details', lessDetails: 'Less details', noInformation: 'Nothing here yet', total: 'Total', cardItem: 'Item' }; function setBatchToggleIcon() {}</script>
<script src="<?= $t('js/custom.js') ?>"></script>
<script src="<?= $t('js/dt-mobile-cards.js') ?>"></script>
<script src="<?= base_url('assets/js/admin.js') . '?v=' . @filemtime(FCPATH . 'assets/js/admin.js') ?>"></script>
