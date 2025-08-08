document.addEventListener("DOMContentLoaded", function () {
  // Heart Rate Chart
  const heartRateCtx = document
    .getElementById("heartRateChart")
    .getContext("2d");
  const heartRateChart = new Chart(heartRateCtx, {
    type: "line",
    data: {
      labels: [],
      datasets: [
        {
          label: "Heart Rate (bpm)",
          data: [],
          borderColor: "#b92929ff",
          backgroundColor: "rgba(219, 52, 52, 0.3)",
          borderWidth: 2,
          fill: false, // Don't fill under the line
          tension: 0, // 🔥 No smoothing (sharp spikes)
          pointRadius: 4, // Show dots
          pointBackgroundColor: "#b92929ff",
          stepped: false, // Set to true for blocky step-style (optional)
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      scales: {
        x: {
          title: { display: true, text: "Time" },
          ticks: { maxRotation: 0, autoSkip: true },
        },
        y: {
          title: { display: true, text: "Heart Rate (bpm)" },
          min: 50,
          max: 120,
        },
      },
      plugins: {
        legend: { display: true },
        tooltip: { enabled: true },
      },
    },
  });

  // Generate Dummy Vitals
  function generateDummyVitals() {
    return {
      mother_heart_rate: 60 + Math.random() * 40,
      mother_bp_sys: 110 + Math.floor(Math.random() * 20),
      mother_bp_dia: 70 + Math.floor(Math.random() * 15),
      mother_temp: 36.5 + Math.random() * 1.5,
      fetal_movement: 5 + Math.floor(Math.random() * 10),
      mother_oxygen: 92 + Math.random() * 8,
    };
  }

  function updateChart(chart, labels, data, newValue) {
    const now = new Date().toLocaleTimeString();
    labels.push(now);
    data.push(newValue);
    if (labels.length > 30) {
      labels.shift();
      data.shift();
    }
    chart.update();
  }
  let sessionVitals = [];

  function updateVitalsUI() {
    const vitals = generateDummyVitals();
    sessionVitals.push(vitals);

    document.getElementById(
      "mother_ecg_stats"
    ).innerText = `Rate: ${vitals.mother_heart_rate.toFixed(0)} bpm`;
    document.getElementById(
      "bp_mother"
    ).innerText = `${vitals.mother_bp_sys} / ${vitals.mother_bp_dia} mmHg`;
    document.getElementById(
      "temperature_mother"
    ).innerText = `${vitals.mother_temp.toFixed(1)} °C`;
    document.getElementById(
      "fetal_movement"
    ).innerText = `${vitals.fetal_movement} kicks/min`;
    document.getElementById(
      "oxygen_mother"
    ).innerText = `${vitals.mother_oxygen.toFixed(0)}%`;

    let suggestions = [];
    if (vitals.mother_heart_rate < 60)
      suggestions.push(
        "Heart rate is low. Consider checking for dizziness or fatigue."
      );
    else if (vitals.mother_heart_rate > 100)
      suggestions.push(
        "Heart rate is high. Rest and hydration are recommended."
      );
    if (vitals.mother_bp_sys > 130 || vitals.mother_bp_dia > 85)
      suggestions.push("Blood pressure is slightly elevated.");
    if (vitals.mother_temp > 37.5)
      suggestions.push(
        "Body temperature is slightly high. Check for fever and stay hydrated."
      );
    if (vitals.mother_oxygen < 95)
      suggestions.push(
        "Oxygen level is low. Consider deep breathing exercises or using supplemental oxygen if necessary."
      );
    if (suggestions.length === 0)
      suggestions.push("Vitals are stable. Keep monitoring.");
    document.getElementById("health_suggestions").innerHTML = suggestions
      .map((s) => `<p>${s}</p>`)
      .join("");

    const now = new Date();
    document.getElementById("last-update").textContent = now.toLocaleTimeString(
      [],
      { hour: "2-digit", minute: "2-digit" }
    );

    // Update heart rate chart
    updateChart(
      heartRateChart,
      heartRateChart.data.labels,
      heartRateChart.data.datasets[0].data,
      vitals.mother_heart_rate
    );
  }

  setInterval(updateVitalsUI, 5000);
  updateVitalsUI();

  // Logout button
  document.getElementById("logoutButton").addEventListener("click", function () {
  if (!patientId) {
    alert("Missing patient ID. Cannot save vitals.");
    return;
  }

  if (sessionVitals.length === 0) {
    window.location.href = "logout.php";
    return;
  }

  const avg = sessionVitals.reduce((acc, curr) => {
    acc.heart_rate += curr.mother_heart_rate;
    acc.bp_sys += curr.mother_bp_sys;
    acc.bp_dia += curr.mother_bp_dia;
    acc.temp += curr.mother_temp;
    acc.fetal_movement += curr.fetal_movement;
    acc.oxygen += curr.mother_oxygen;
    return acc;
  }, {
    heart_rate: 0,
    bp_sys: 0,
    bp_dia: 0,
    temp: 0,
    fetal_movement: 0,
    oxygen: 0
  });

  const n = sessionVitals.length;
  const averagedData = {
    patient_id: patientId,
    heart_rate: (avg.heart_rate / n).toFixed(1),
    blood_pressure: `${Math.round(avg.bp_sys / n)}/${Math.round(avg.bp_dia / n)}`,
    body_temperature: (avg.temp / n).toFixed(1),
    fetal_movement: Math.round(avg.fetal_movement / n),
    oxygen_saturation: Math.round(avg.oxygen / n),
    notes: "Auto-logged from session",
    status: "normal"
  };

  fetch("save_vitals.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(averagedData)
  })
  .then(res => res.text())
  .then(text => {
    console.log("Response from save_vitals.php:", text);
    if (text.includes("Vitals saved successfully")) {
      window.location.href = "logout.php";
    } else {
      alert("Vitals not saved:\n" + text);
    }
  })
  .catch(err => {
    console.error("Error during save:", err);
    alert("Error saving vitals. Logout aborted.");
  });
});




  function sendAveragedVitalsToServer(averagedVitals) {
    fetch("save_vitals.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(averagedData),
    })
      .then((response) => response.text())
      .then((result) => {
        console.log("Save result:", result);
        if (result.includes("Vitals saved successfully")) {
          window.location.href = "logout.php";
        } else {
          alert("Error saving vitals before logout:\n" + result);
        }
      })
      .catch((error) => {
        console.error("Save error:", error);
        alert("Could not save vitals before logout.");
      });
  }

  // Chat toggle
  window.toggleChat = function () {
    const chatbox = document.getElementById("chatbox");
    chatbox.style.display =
      chatbox.style.display === "none" || chatbox.style.display === ""
        ? "block"
        : "none";
  };
});
