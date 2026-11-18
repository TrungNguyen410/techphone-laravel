/*
 * Biểu đồ trang tổng quan quản trị (Chart.js).
 * Doanh thu năm / tháng / ngày vẽ dạng wireframe: đường mảnh, điểm rỗng, không tô nền,
 * lưới nét đứt. Màu lấy từ design token CSS (đổi oklch sang rgb để Chart.js tính được màu hover).
 */
(() => {
  if (!window.Chart) return;
  const styles = getComputedStyle(document.documentElement);
  const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
  // Vẽ 1 điểm ảnh bằng màu token rồi đọc lại, nên mọi cú pháp màu trình duyệt hỗ trợ đều thành rgb.
  const toRgb = (color, fallback) => {
    if (!probe || !color) return fallback;
    probe.clearRect(0, 0, 1, 1);
    probe.fillStyle = fallback;
    probe.fillStyle = color;
    probe.fillRect(0, 0, 1, 1);
    const [r, g, b] = probe.getImageData(0, 0, 1, 1).data;
    return `rgb(${r}, ${g}, ${b})`;
  };
  const token = (name, fallback) => toRgb(styles.getPropertyValue(name).trim(), fallback);
  const colors = {
    accent: token('--color-accent', '#2563eb'),
    accentStrong: token('--color-accent-strong', '#1e40af'),
    danger: token('--color-danger', '#dc2626'),
    rule: token('--color-rule', '#e5e7eb'),
    muted: token('--color-ink-muted', '#64748b'),
    paper: token('--color-static-white', '#ffffff'),
    success: token('--color-success', '#16a34a'),
    violet: token('--color-violet', '#7c3aed'),
    warning: token('--color-warning', '#d97706'),
  };
  Chart.defaults.color = colors.muted;
  Chart.defaults.font.family = styles.getPropertyValue('--font-body').trim() || 'sans-serif';

  const number = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 });
  const money = (value) => `${new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0))} ₫`;
  // Nhãn trục ngắn gọn ("1,5 tỷ", "250 tr"); tooltip và bảng số liệu hiện số tiền chính xác.
  const compactMoney = (value) => {
    const amount = Number(value) || 0;
    if (Math.abs(amount) >= 1e9) return `${number.format(amount / 1e9)} tỷ`;
    if (Math.abs(amount) >= 1e6) return `${number.format(amount / 1e6)} tr`;
    if (Math.abs(amount) >= 1e3) return `${number.format(amount / 1e3)}K`;
    return number.format(amount);
  };

  document.querySelectorAll('[data-revenue-chart]').forEach((canvas) => {
    const values = JSON.parse(canvas.dataset.values || '[]');
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const tips = JSON.parse(canvas.dataset.tips || '[]');
    const total = values.reduce((sum, value) => sum + value, 0);
    const dense = values.length > 16;

    new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Doanh thu',
          data: values,
          fill: false,
          borderColor: colors.accent,
          borderWidth: 1.5,
          tension: 0,
          pointStyle: 'circle',
          pointRadius: dense ? 2.5 : 3.5,
          pointBorderWidth: 1.5,
          pointBorderColor: colors.accent,
          pointBackgroundColor: colors.paper,
          pointHoverRadius: 6,
          pointHoverBorderWidth: 2,
          pointHoverBorderColor: colors.accentStrong,
          pointHoverBackgroundColor: colors.paper,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            displayColors: false,
            callbacks: {
              title: (items) => tips[items[0].dataIndex] || items[0].label,
              label: (item) => money(item.parsed.y),
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            // Giai đoạn chưa có doanh thu vẫn có thang tiền thay vì 0 / 0,5 / 1.
            suggestedMax: total === 0 ? 1000000 : undefined,
            border: { display: false },
            // Lưới ngang được vẽ bằng nét đứt ở plugin dashedGrid bên dưới.
            grid: { display: false },
            ticks: { maxTicksLimit: 5, callback: (value) => compactMoney(value) },
          },
          x: {
            border: { color: colors.rule },
            grid: { display: false },
            ticks: { autoSkip: true, maxRotation: 0, maxTicksLimit: dense ? 11 : 12 },
          },
        },
      },
      plugins: [{
        // Lưới ngang nét đứt cho đúng kiểu wireframe.
        id: 'dashedGrid',
        beforeDatasetsDraw: (chart) => {
          const { ctx, chartArea, scales } = chart;
          ctx.save();
          ctx.setLineDash([4, 4]);
          ctx.strokeStyle = colors.rule;
          ctx.lineWidth = 1;
          scales.y.ticks.forEach((_, index) => {
            const y = scales.y.getPixelForTick(index);
            ctx.beginPath();
            ctx.moveTo(chartArea.left, y);
            ctx.lineTo(chartArea.right, y);
            ctx.stroke();
          });
          ctx.restore();
        },
      }],
    });
  });
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
