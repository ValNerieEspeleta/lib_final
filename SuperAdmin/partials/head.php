<meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SRC Library</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="static/vendors/feather/feather.css">
  <link rel="stylesheet" href="static/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="static/vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="static/vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="static/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="static/=js/select.dataTables.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="static/css/vertical-layout-light/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="static/images/srclogo.png" />

  <link rel="stylesheet" href="static/css/rfidani.css">
  <link rel="stylesheet" href="static/css/loanbook.css">
  <link rel="stylesheet" href="../static/css/dark-mode.css">
  <script>
    (function() {
      const theme = localStorage.getItem('theme');
      if (theme === 'dark') {
        document.documentElement.classList.add('dark-mode');
        // Add class to body as soon as it's available
        const observer = new MutationObserver(function(mutations) {
          if (document.body) {
            document.body.classList.add('dark-mode');
            observer.disconnect();
          }
        });
        observer.observe(document.documentElement, { childList: true });
      }
    })();
  </script>