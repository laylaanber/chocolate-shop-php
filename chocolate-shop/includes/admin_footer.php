</div>
  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; <?= date('Y') ?> <a href="../index.php">Chocolate Shop</a></strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.0.0
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.1/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<!-- Custom Script -->
<script>
  $(document).ready(function() {
    // Fix sidebar active state
    document.body.classList.add('layout-fixed');
    
    // Initialize any DataTables
    if ($.fn.dataTable && $('.datatable').length > 0) {
      $('.datatable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "language": {
          "search": "Search:",
          "lengthMenu": "Show _MENU_ entries per page",
          "info": "Showing _START_ to _END_ of _TOTAL_ entries",
          "infoEmpty": "Showing 0 to 0 of 0 entries",
          "zeroRecords": "No matching records found"
        }
      });
    }
    
    // Custom tooltips
    $('[data-toggle="tooltip"]').tooltip();
  });
</script>

</body>
</html>