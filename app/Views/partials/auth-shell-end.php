        </div>
      </main>

      <footer class="app-footer">
        <div class="mx-auto w-full max-w-[1280px]">
          Albion Crafting Profit Calculator | Tailwind local build | No CDN
        </div>
      </footer>
    </div>
  </div>

  <script>
    (function () {
      const sidebar = document.querySelector('.app-sidebar');
      const sidebarBackdrop = document.getElementById('sidebar-backdrop');
      const toggleSidebarBtn = document.getElementById('toggle-sidebar');
      const closeSidebarBtn = document.getElementById('close-sidebar');

      function openSidebar() {
        if (!sidebar || !sidebarBackdrop) return;
        sidebar.classList.add('is-open');
        sidebarBackdrop.classList.add('is-open');
      }

      function closeSidebar() {
        if (!sidebar || !sidebarBackdrop) return;
        sidebar.classList.remove('is-open');
        sidebarBackdrop.classList.remove('is-open');
      }

      if (toggleSidebarBtn) toggleSidebarBtn.addEventListener('click', openSidebar);
      if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
      if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);
    })();
  </script>
</body>
</html>
