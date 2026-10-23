/* Biểu đồ trang tổng quan quản trị (Chart.js), màu lấy từ design token CSS. */
(() => {
  if (!window.Chart) return;
  const styles = getComputedStyle(document.documentElement);
  const token = (name) => styles.getPropertyValue(name).trim();
  const colors = {
    accent: token('--color-accent'),
    danger: token('--color-danger'),
    rule: token('--color-rule-soft'),
    success: token('--color-success'),
    violet: token('--color-violet'),
    warning: token('--color-warning'),
  };

  const revenue = document.getElementById('revenue-chart');
  if (revenue) {
    new Chart(revenue, {
      type: 'bar',
      data: {
        labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
        datasets: [{ label: 'Doanh thu (triệu đồng)', data: JSON.parse(revenue.dataset.values), backgroundColor: colors.accent, borderRadius: 7 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: colors.rule } }, x: { grid: { display: false } } },
      },
    });
  }

  const status = document.getElementById('status-chart');
  if (status) {
    new Chart(status, {
      type: 'doughnut',
      data: {
        labels: JSON.parse(status.dataset.labels),
        datasets: [{
          data: JSON.parse(status.dataset.values),
          backgroundColor: [colors.warning, colors.accent, colors.violet, colors.success, colors.danger],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
      },
    });
  }
})();
