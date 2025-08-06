const ecgMotherCtx = document.getElementById("ecgMotherChart").getContext("2d");
const ecgFetalCtx = document.getElementById("ecgFetalChart").getContext("2d");

const chartOptions = {
  animation: false,
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      grid: {
        display: false,
      },
      ticks: {
        maxTicksLimit: 8,
        color: "#666",
        font: {
          size: 10,
        },
      },
    },
    y: {
      grid: {
        color: "rgba(0,0,0,0.05)",
        drawBorder: false,
      },
      ticks: {
        maxTicksLimit: 5,
        color: "#666",
        font: {
          size: 10,
        },
      },
      min: -0.5,
      max: 2.5,
    },
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      enabled: false,
    },
  },
  elements: {
    line: {
      tension: 0.4,
      borderWidth: 1.5,
      fill: false,
    },
    point: {
      radius: 0,
    },
  },
};

const motherChartGradient = ecgMotherCtx.createLinearGradient(0, 0, 0, 200);
motherChartGradient.addColorStop(0, "#e74c3c");
motherChartGradient.addColorStop(1, "#e74c3c80");

const fetalChartGradient = ecgFetalCtx.createLinearGradient(0, 0, 0, 200);
fetalChartGradient.addColorStop(0, "#3498db");
fetalChartGradient.addColorStop(1, "#3498db80");

const ecgMotherChart = new Chart(ecgMotherCtx, {
  type: "line",
  data: {
    labels: [],
    datasets: [
      {
        label: "Mother ECG",
        borderColor: "#e74c3c",
        backgroundColor: motherChartGradient,
        borderWidth: 1.5,
        pointRadius: 0,
        data: [],
        fill: "start",
        tension: 0.4,
      },
    ],
  },
  options: chartOptions,
});

const ecgFetalChart = new Chart(ecgFetalCtx, {
  type: "line",
  data: {
    labels: [],
    datasets: [
      {
        label: "Fetal ECG",
        borderColor: "#3498db",
        backgroundColor: fetalChartGradient,
        borderWidth: 1.5,
        pointRadius: 0,
        data: [],
        fill: "start",
        tension: 0.4,
      },
    ],
  },
  options: chartOptions,
});
function updateHealthSuggestions() {
  const motherECG = 55;
  const fetalECG = 170;
  const motherTemp = 38.2;
  const fetalTemp = 39.0;
  const oxygenMother = 93.0;

  console.log({ motherECG, fetalECG, motherTemp, fetalTemp, oxygenMother });

  let suggestions = [];

  if (
    isNaN(motherECG) ||
    isNaN(fetalECG) ||
    isNaN(motherTemp) ||
    isNaN(fetalTemp) ||
    isNaN(oxygenMother)
  ) {
    document.getElementById("health_suggestions").innerHTML =
      "Waiting for valid vitals data...";
    return;
  }

  // ECG Analysis (Mother)
  if (motherECG < 60) {
    suggestions.push(
      "Mother's heart rate is low. Consider checking for dizziness or fatigue."
    );
  } else if (motherECG > 100) {
    suggestions.push(
      "Mother's heart rate is high. Rest and hydration are recommended."
    );
  }

  // ECG Analysis (Fetal)
  if (fetalECG < 110) {
    suggestions.push(
      "Fetal heart rate is low. Monitor closely and consider consulting a doctor."
    );
  } else if (fetalECG > 160) {
    suggestions.push(
      "Fetal heart rate is high. Ensure the mother is well-hydrated and resting."
    );
  }

  // Temperature Analysis
  if (motherTemp > 37.5) {
    suggestions.push(
      "Mother's temperature is slightly high. Check for fever and stay hydrated."
    );
  }
  if (fetalTemp > 38) {
    suggestions.push(
      "Fetal temperature is high. Immediate medical attention may be needed."
    );
  }

  // Oxygen Level Analysis
  if (oxygenMother < 95) {
    suggestions.push(
      "Mother's oxygen level is low. Consider deep breathing exercises or using supplemental oxygen if necessary."
    );
  }

  console.log("Suggestions:", suggestions);
  console.log("Mother ECG:", motherECG); // Expected: number like 70–100
  console.log("Fetal ECG:", fetalECG); // Expected: number like 120–160
  console.log("Mother Temp:", motherTemp); // Expected: number like 36–38
  console.log("Fetal Temp:", fetalTemp); // Expected: number like 37–38
  console.log("Oxygen Mother:", oxygenMother); // Expected: number like 95–99

  // Display suggestions
  document.getElementById("health_suggestions").innerHTML =
    suggestions.length > 0
      ? suggestions.join("<br>")
      : "Vitals are stable. No immediate action required.";
}

function updateData() {
  const dummyECG = () => Math.random() * 2;
  const currentTime = new Date().toLocaleTimeString();

  // Update mother ECG
  ecgMotherChart.data.labels.push(currentTime);
  const motherValue = dummyECG();
  ecgMotherChart.data.datasets[0].data.push(motherValue);

  // Update fetal ECG
  ecgFetalChart.data.labels.push(currentTime);
  const fetalValue = dummyECG();
  ecgFetalChart.data.datasets[0].data.push(fetalValue);

  // Keep last 50 data points
  if (ecgMotherChart.data.labels.length > 50) {
    ecgMotherChart.data.labels.shift();
    ecgMotherChart.data.datasets[0].data.shift();
    ecgFetalChart.data.labels.shift();
    ecgFetalChart.data.datasets[0].data.shift();
  }

  // Update stats
  document.getElementById("mother_ecg_stats").innerText = `Rate: ${Math.floor(
    Math.random() * 20 + 70
  )} bpm`;
  document.getElementById("fetal_ecg_stats").innerText = `Rate: ${Math.floor(
    Math.random() * 40 + 120
  )} bpm`;

  // Update metrics
  document.getElementById("heart_rate_fetal").innerText =
    Math.floor(Math.random() * 40 + 50) + " bpm";
  document.getElementById("temperature_mother").innerText =
    (36 + Math.random()).toFixed(1) + " °C";
  document.getElementById("temperature_fetal").innerText =
    (37 + Math.random()).toFixed(1) + " °C";
  document.getElementById("oxygen_mother").innerText =
    (96 + Math.random() * 4).toFixed(1) + "%";

  ecgMotherChart.update();
  ecgFetalChart.update();
  updateLastUpdate();
  updateHealthSuggestions();
}

function updateLastUpdate() {
  const now = new Date();
  document.getElementById("last-update").textContent = now.toLocaleTimeString(
    [],
    { hour: "2-digit", minute: "2-digit" }
  );
}

setInterval(updateData, 5000);

// Log out button functionality
document.getElementById("logoutButton").addEventListener("click", function () {
  // Redirect to login page or perform logout logic
  window.location.href = "login.html"; // Example redirect to login page
});

// Toggle chat window visibility
function toggleChat() {
  const chatbox = document.getElementById("chatbox");
  chatbox.style.display =
    chatbox.style.display === "none" || chatbox.style.display === ""
      ? "block"
      : "none";
}
