// Globale Variable für Appointment ID hinzufügen
let currentAppointmentId = null;

let currentStep = 1;
let bookingData = {
  customer: {},
  services: [],
  date: null,
  time: null,
  distance: 0,
  travelCost: 0,
  subtotal: 0,
  total: 0,
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  loadServices();
  initCalendar();
  initAddressAutocomplete();
});

// Manual distance calculation button
function calculateDistanceFromInput() {
  const addressInput = document.getElementById("address");
  if (addressInput && addressInput.value.trim()) {
    calculateDistance(addressInput.value.trim());
  } else {
    const distanceInfo = document.getElementById("distance-info");
    const distanceText = document.getElementById("distance-text");
    distanceText.innerHTML =
      "<strong>Bitte geben Sie eine Adresse ein</strong>";
    distanceInfo.className = "alert alert-warning";
    distanceInfo.style.display = "block";
  }
}

// Google Maps Address Autocomplete
function initAddressAutocomplete() {
  const addressInput = document.getElementById("address");
  if (!addressInput) return;

  // Check if Google Maps is available
  if (typeof google !== "undefined" && google.maps) {
    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
      types: ["address"],
      componentRestrictions: { country: "de" },
    });

    autocomplete.addListener("place_changed", function () {
      const place = autocomplete.getPlace();
      if (place.geometry) {
        calculateDistance(place.formatted_address);
      }
    });
  }

  // Also calculate distance when user leaves the address field (for manual entry)
  addressInput.addEventListener("blur", function () {
    if (
      this.value.trim() &&
      (!bookingData.distance || bookingData.distance === 0)
    ) {
      calculateDistance(this.value.trim());
    }
  });

  // Add event listener for Enter key
  addressInput.addEventListener("keypress", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      if (this.value.trim()) {
        calculateDistance(this.value.trim());
      }
    }
  });
}

// Calculate distance from company to customer
async function calculateDistance(customerAddress) {
  try {
    // Show loading state
    const distanceInfo = document.getElementById("distance-info");
    const distanceText = document.getElementById("distance-text");
    distanceInfo.className = "alert alert-info";
    distanceText.innerHTML = "Entfernung wird berechnet...";
    distanceInfo.style.display = "block";

    const response = await fetch("api/calculate-distance.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ address: customerAddress }),
    });

    const data = await response.json();

    if (data.success) {
      bookingData.distance = data.distance;
      bookingData.travelCost = data.travelCost;

      let infoHtml = `<strong>Entfernung: ${data.distance.toFixed(
        1
      )} km</strong>`;

      // Show if using Google Maps or demo mode
      if (data.usingGoogleMaps) {
        infoHtml += ` <small style="color: var(--clr-success);">(✓ Google Maps)</small>`;
        if (data.duration) {
          infoHtml += `<br><small>Fahrtzeit: ca. ${data.duration} Minuten</small>`;
        }
      } else if (data.message) {
        infoHtml += ` <small style="color: var(--clr-warning);">(Demo-Modus)</small>`;
      }

      if (data.distance > 35) {
        distanceText.innerHTML =
          infoHtml +
          `<br>
                    <strong style="color: var(--clr-error);">Leider außerhalb unseres Servicegebiets (max. 35km)</strong>`;
        distanceInfo.className = "alert alert-error";
        document.querySelector('[onclick="nextStep()"]').disabled = true;
      } else if (data.distance > 10) {
        distanceText.innerHTML =
          infoHtml +
          `<br>
                    Anfahrtskosten: <strong>${data.travelCost.toFixed(
                      2
                    )}€</strong><br>
                    Mindestbestellwert: <strong>59,90€</strong>`;
        distanceInfo.className = "alert alert-warning";
        document.querySelector('[onclick="nextStep()"]').disabled = false;
      } else {
        distanceText.innerHTML =
          infoHtml +
          `<br>
                    <strong style="color: var(--clr-success);">Kostenlose Anfahrt!</strong>`;
        distanceInfo.className = "alert alert-success";
        document.querySelector('[onclick="nextStep()"]').disabled = false;
      }
    } else {
      // Error occurred
      distanceText.innerHTML = `<strong>Fehler:</strong> ${
        data.error || "Entfernung konnte nicht berechnet werden"
      }`;
      distanceInfo.className = "alert alert-error";
      bookingData.distance = 0;
      document.querySelector('[onclick="nextStep()"]').disabled = true;
    }

    distanceInfo.style.display = "block";
  } catch (error) {
    console.error("Error calculating distance:", error);
    const distanceInfo = document.getElementById("distance-info");
    const distanceText = document.getElementById("distance-text");
    distanceText.innerHTML =
      "<strong>Fehler:</strong> Verbindung zum Server fehlgeschlagen";
    distanceInfo.className = "alert alert-error";
    distanceInfo.style.display = "block";
  }
}

// Load services
async function loadServices() {
  try {
    const response = await fetch("api/get-services.php");
    const services = await response.json();

    const grid = document.getElementById("services-grid");
    if (grid) {
      grid.innerHTML = services
        .map(
          (service) => `
                <div class="service-card" onclick="toggleService(${
                  service.id
                })" data-service-id="${service.id}">
                    <div class="service-card-bg" style="background-image: url('assets/images/services/${
                      service.background_image || "default.jpg"
                    }')"></div>
                    <div class="service-card-content">
                        <div class="service-card-top">
                            <h3>${service.name}</h3>
                            <p class="service-card-description">${
                              service.description || ""
                            }</p>
                        </div>
                        <div class="service-card-meta">
                            <span class="service-price">${parseFloat(
                              service.price
                            ).toFixed(2)}€</span>
                            <span class="service-duration">${
                              service.duration_minutes
                            } Min.</span>
                        </div>
                    </div>
                </div>
            `
        )
        .join("");

      // Store services data globally
      window.servicesData = services;
    }
  } catch (error) {
    console.error("Error loading services:", error);
  }
}

// Toggle service selection
function toggleService(serviceId) {
  // Ensure serviceId is a number for comparison
  serviceId = parseInt(serviceId);

  const card = document.querySelector(`[data-service-id="${serviceId}"]`);

  // Make sure servicesData is loaded
  if (!window.servicesData || !Array.isArray(window.servicesData)) {
    console.error("Services data not loaded yet");
    return;
  }

  // Find service and ensure ID comparison works with both strings and numbers
  const service = window.servicesData.find((s) => parseInt(s.id) === serviceId);

  if (!service) {
    console.error("Service not found with ID:", serviceId);
    return;
  }

  if (card.classList.contains("selected")) {
    // Remove service
    card.classList.remove("selected");
    bookingData.services = bookingData.services.filter(
      (s) => parseInt(s.id) !== serviceId
    );
  } else {
    // Add service
    card.classList.add("selected");
    bookingData.services.push(service);
  }

  updateServicesSummary();
}

// Update services summary
// Update services summary - SAFER VERSION
function updateServicesSummary() {
  // Ensure services array exists and has valid items
  if (!bookingData.services || bookingData.services.length === 0) {
    document.getElementById("total-duration").textContent = "0 Min.";
    document.getElementById("subtotal").textContent = "0,00€";
    bookingData.subtotal = 0;

    // Hide minimum price warning if no services selected
    const warning = document.getElementById("min-price-warning");
    if (warning) {
      warning.style.display = "none";
    }
    return;
  }

  // Calculate with safety checks
  const totalDuration = bookingData.services.reduce((sum, s) => {
    // Check if service exists and has duration_minutes
    if (s && s.duration_minutes) {
      return sum + parseInt(s.duration_minutes);
    }
    console.warn("Service missing duration_minutes:", s);
    return sum;
  }, 0);

  const subtotal = bookingData.services.reduce((sum, s) => {
    // Check if service exists and has price
    if (s && s.price) {
      return sum + parseFloat(s.price);
    }
    console.warn("Service missing price:", s);
    return sum;
  }, 0);

  document.getElementById(
    "total-duration"
  ).textContent = `${totalDuration} Min.`;
  document.getElementById("subtotal").textContent = `${subtotal.toFixed(2)}€`;

  bookingData.subtotal = subtotal;

  // Reset time selection if duration changed (services were added/removed)
  if (bookingData.time) {
    // Clear time selection as available slots might have changed
    bookingData.time = null;
    const timeSlots = document.querySelectorAll(".time-slot.selected");
    timeSlots.forEach((slot) => slot.classList.remove("selected"));

    // Show info message if we're on the time selection step
    if (currentStep === 4) {
      const container = document.getElementById("time-slots");
      if (container) {
        container.innerHTML =
          '<p style="text-align: center; color: var(--clr-warning);">Services wurden geändert. Bitte Datum erneut auswählen.</p>';
      }
    }
  }

  // Check minimum price for distance > 10km
  if (bookingData.distance > 10) {
    const warning = document.getElementById("min-price-warning");
    if (warning) {
      if (subtotal < 59.9) {
        warning.style.display = "block";
        warning.innerHTML = `<strong>Hinweis:</strong> Bei einer Entfernung über 10km beträgt der Mindestbestellwert 59,90€. 
                  Aktuell: ${subtotal.toFixed(2)}€ - Es fehlen noch ${(
          59.9 - subtotal
        ).toFixed(2)}€`;
      } else {
        warning.style.display = "none";
      }
    }
  }
}

let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let blockedDaysData = { fullyBlocked: [], partiallyBlocked: [] };

function initCalendar() {
  loadBlockedDays().then(() => {
    renderCalendar();
  });
}

// Load blocked days for current month
async function loadBlockedDays() {
  const monthStr = `${currentYear}-${String(currentMonth + 1).padStart(
    2,
    "0"
  )}`;

  try {
    const response = await fetch("api/check-blocked-days.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ month: monthStr }),
    });

    const data = await response.json();
    if (data.success) {
      blockedDaysData = data;
    }
  } catch (error) {
    console.error("Error loading blocked days:", error);
    blockedDaysData = { fullyBlocked: [], partiallyBlocked: [] };
  }
}

function renderCalendar() {
  const monthNames = [
    "Januar",
    "Februar",
    "März",
    "April",
    "Mai",
    "Juni",
    "Juli",
    "August",
    "September",
    "Oktober",
    "November",
    "Dezember",
  ];
  const dayNames = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"];

  document.getElementById(
    "calendar-month"
  ).textContent = `${monthNames[currentMonth]} ${currentYear}`;

  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  let html = dayNames
    .map(
      (day) =>
        `<div style="font-weight: bold; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--clr-primary-a40);">${day}</div>`
    )
    .join("");

  // Empty cells before first day
  for (let i = 0; i < firstDay; i++) {
    html += "<div></div>";
  }

  // Days of month
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(currentYear, currentMonth, day);
    date.setHours(0, 0, 0, 0);

    // Format date as YYYY-MM-DD for consistency
    const dateStr = formatDateForDB(date);

    const isPast = date < today;
    const isSunday = date.getDay() === 0;
    const isToday = date.getTime() === today.getTime();
    const isFullyBlocked = blockedDaysData.fullyBlocked.includes(dateStr);
    const isPartiallyBlocked =
      blockedDaysData.partiallyBlocked.includes(dateStr);

    let classes = "calendar-day";
    if (isPast || isSunday || isFullyBlocked) classes += " disabled";
    if (isToday) classes += " today";
    if (bookingData.date === dateStr) classes += " selected";
    if (isPartiallyBlocked && !isFullyBlocked) classes += " partially-blocked";

    let title = "";
    let indicator = "";

    if (isPast) {
      title = "Vergangenes Datum";
    } else if (isSunday) {
      title = "Sonntags geschlossen";
    } else if (isFullyBlocked) {
      title = "Dieser Tag ist komplett blockiert";
      indicator =
        '<span style="position: absolute; top: 2px; right: 2px; color: var(--clr-error); font-size: 0.7rem;">✖</span>';
    } else if (isPartiallyBlocked) {
      title = "Einige Zeiten sind an diesem Tag blockiert";
      indicator =
        '<span style="position: absolute; top: 2px; right: 2px; color: var(--clr-warning); font-size: 0.7rem;">⚠</span>';
    }

    const isClickable = !isPast && !isSunday && !isFullyBlocked;

    html += `<div class="${classes}" 
                      onclick="${isClickable ? `selectDate('${dateStr}')` : ""}"
                      data-date="${dateStr}"
                      title="${title}"
                      style="position: relative;">
                    ${day}
                    ${indicator}
                 </div>`;
  }

  document.getElementById("calendar-grid").innerHTML = html;
}

// Format date for database (YYYY-MM-DD)
function formatDateForDB(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function changeMonth(direction) {
  currentMonth += direction;
  if (currentMonth < 0) {
    currentMonth = 11;
    currentYear--;
  } else if (currentMonth > 11) {
    currentMonth = 0;
    currentYear++;
  }

  // Load blocked days for new month before rendering
  loadBlockedDays().then(() => {
    renderCalendar();
  });
}

// Add some CSS for partially blocked days
const style = document.createElement("style");
style.textContent = `
  .calendar-day.partially-blocked {
    background: rgba(255, 152, 0, 0.1);
    border-color: var(--clr-warning);
  }
  
  .calendar-day.partially-blocked:hover:not(.disabled) {
    background: rgba(255, 152, 0, 0.2);
  }
  
  .calendar-day.disabled {
    background: rgba(255, 255, 255, 0.02);
    cursor: not-allowed !important;
    opacity: 0.3;
  }
`;
document.head.appendChild(style);

function selectDate(dateStr) {
  // Remove previous selection
  document.querySelectorAll(".calendar-day.selected").forEach((el) => {
    el.classList.remove("selected");
  });

  // Add selection
  const dayElement = document.querySelector(`[data-date="${dateStr}"]`);
  if (dayElement) {
    dayElement.classList.add("selected");
  }

  bookingData.date = dateStr;

  // Enable continue button
  document.getElementById("date-continue").disabled = false;

  // Load available time slots
  loadTimeSlots(dateStr);
}

// Load available time slots
// Load available time slots
async function loadTimeSlots(date) {
  const dateObj = new Date(date + "T00:00:00");
  const dayOfWeek = dateObj.getDay();

  // Get working hours from data attributes (set by PHP)
  const weekdayStart =
    document.getElementById("time-slots").dataset.weekdayStart || "16:30";
  const weekdayEnd =
    document.getElementById("time-slots").dataset.weekdayEnd || "21:00";
  const saturdayStart =
    document.getElementById("time-slots").dataset.saturdayStart || "09:00";
  const saturdayEnd =
    document.getElementById("time-slots").dataset.saturdayEnd || "14:00";
  const slotDuration = parseInt(
    document.getElementById("time-slots").dataset.slotDuration || "30"
  );

  // Calculate total duration of selected services
  const totalDuration = bookingData.services.reduce(
    (sum, s) => sum + parseInt(s.duration_minutes),
    0
  );

  if (totalDuration === 0) {
    document.getElementById("time-slots").innerHTML =
      '<p style="text-align: center; color: var(--clr-warning);">Bitte wählen Sie zuerst Services aus.</p>';
    return;
  }

  if (dayOfWeek === 0) {
    // Sunday - closed
    document.getElementById("time-slots").innerHTML =
      '<p style="text-align: center; color: var(--clr-error);">Sonntags geschlossen</p>';
    return;
  }

  // Show loading state
  document.getElementById("time-slots").innerHTML =
    '<p style="text-align: center;">Verfügbare Termine werden geprüft...</p>';

  // Check availability with the new improved API
  try {
    const response = await fetch("api/check-availability.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        date: date,
        duration: totalDuration,
      }),
    });

    const result = await response.json();

    // Render time slots based on result
    const container = document.getElementById("time-slots");

    if (!result.available) {
      // No slots available for this duration
      container.innerHTML = `
        <div class="alert alert-warning" style="grid-column: 1 / -1;">
          <strong>Keine passenden Termine verfügbar</strong><br>
          ${
            result.message ||
            "An diesem Tag ist nicht genügend zusammenhängende Zeit für Ihre gewählten Services verfügbar."
          }
          <br><br>
          ${result.info ? `<small>${result.info}</small><br>` : ""}
          <small>Benötigte Zeit: ${totalDuration} Minuten (+ Vor-/Nachbereitungszeit)<br>
          Bitte wählen Sie einen anderen Tag.</small>
        </div>
      `;

      // Disable continue button
      document.getElementById("time-continue").disabled = true;
    } else if (result.blocked === "all") {
      // Entire day is blocked
      container.innerHTML = `
        <div class="alert alert-error" style="grid-column: 1 / -1;">
          ${result.message || "Dieser Tag ist nicht verfügbar"}
        </div>
      `;
      document.getElementById("time-continue").disabled = true;
    } else if (result.slots && result.slots.length > 0) {
      // Count available slots
      const availableCount = result.slots.filter((s) => s.available).length;

      // Create info header with buffer notice if provided
      const infoHtml = `
        <div style="grid-column: 1 / -1; margin-bottom: 1rem;">
          ${
            result.info
              ? `
            <div class="alert alert-info" style="margin-bottom: 1rem;">
              <strong>ℹ️ Wichtiger Hinweis:</strong><br>
              Für optimale Servicequalität planen wir automatisch 30 Minuten 
              Vor- und Nachbereitungszeit für jeden Termin ein. Dies garantiert 
              Ihnen pünktlichen Service ohne Wartezeiten.
            </div>
          `
              : ""
          }
          <small style="color: var(--clr-primary-a40);">
            <strong>${availableCount} Termine verfügbar</strong> | 
            Service-Dauer: ${totalDuration} Minuten
            ${
              result.info
                ? ` | Gesamtzeit inkl. Puffer: ${totalDuration + 60} Minuten`
                : ""
            }
          </small>
        </div>
      `;

      // Helper function to calculate end time
      const calculateEndTime = (startTime, durationMinutes) => {
        const [hours, minutes] = startTime.split(":").map(Number);
        const totalMinutes = hours * 60 + minutes + durationMinutes;
        const endHours = Math.floor(totalMinutes / 60);
        const endMinutes = totalMinutes % 60;
        return `${String(endHours).padStart(2, "0")}:${String(
          endMinutes
        ).padStart(2, "0")}`;
      };

      // Render available slots in grid with enhanced tooltips
      const slotsHtml = result.slots
        .map((slot) => {
          const isAvailable = slot.available;
          const reasonText = slot.reason || "";

          // Create appropriate tooltip
          let tooltip = "";
          if (isAvailable) {
            const endTime = calculateEndTime(slot.time, totalDuration);
            tooltip = result.info
              ? `Termin: ${slot.time} - ${endTime} Uhr\n(+ 30 Min. Vor-/Nachbereitung)`
              : `Verfügbar: ${slot.time} - ${endTime} Uhr`;
          } else {
            tooltip = reasonText || "Dieser Termin ist nicht verfügbar";
          }

          // Add visual indicator for slots blocked due to buffer time
          const hasBufferConflict =
            reasonText && reasonText.includes("inkl. Puffer");

          return `<div class="time-slot ${!isAvailable ? "disabled" : ""}" 
                       onclick="${
                         isAvailable ? `selectTime('${slot.time}')` : ""
                       }"
                       title="${tooltip}"
                       style="${
                         !isAvailable && reasonText ? "position: relative;" : ""
                       }">
                      ${slot.time}
                      ${
                        hasBufferConflict
                          ? '<span style="position: absolute; top: -5px; right: -5px; background: var(--clr-warning); color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; display: flex; align-items: center; justify-content: center;">⏱</span>'
                          : ""
                      }
                  </div>`;
        })
        .join("");

      // Combine info and slots
      container.innerHTML = infoHtml + slotsHtml;

      // Add legend if we have buffer information
      if (result.info) {
        container.innerHTML += `
          <div style="grid-column: 1 / -1; margin-top: 2rem; padding: 1rem; background: var(--clr-surface-a10); border-radius: 8px;">
            <small style="color: var(--clr-primary-a40);">
              <strong>Legende:</strong><br>
              ✅ <span style="color: var(--clr-success);">Verfügbar</span> - Klicken Sie zur Auswahl<br>
              ⏱️ <span style="color: var(--clr-warning);">Mit Uhr-Symbol</span> - Blockiert wegen Pufferzeit eines anderen Termins<br>
              ❌ <span style="color: var(--clr-error-a60);">Grau</span> - Nicht verfügbar
            </small>
          </div>
        `;
      }
    } else {
      // Fallback for old API response format (backward compatibility)
      const blockedSlots = result;

      let startTime, endTime;
      if (dayOfWeek === 6) {
        startTime = saturdayStart;
        endTime = saturdayEnd;
      } else {
        startTime = weekdayStart;
        endTime = weekdayEnd;
      }

      // Generate time slots
      const slots = [];
      let current = new Date(`2000-01-01 ${startTime}`);
      const end = new Date(`2000-01-01 ${endTime}`);

      while (current < end) {
        const timeStr = current.toTimeString().slice(0, 5);
        slots.push(timeStr);
        current.setMinutes(current.getMinutes() + slotDuration);
      }

      // Create info header
      const infoHtml = `
        <div style="grid-column: 1 / -1; margin-bottom: 1rem;">
          <small style="color: var(--clr-primary-a40);">
            ${slots.length - (blockedSlots.length || 0)} Termine verfügbar | 
            Dauer Ihrer Services: ${totalDuration} Minuten
          </small>
        </div>
      `;

      // Render slots
      const slotsHtml = slots
        .map((slot) => {
          const isBlocked = blockedSlots.includes(slot);
          return `<div class="time-slot ${isBlocked ? "disabled" : ""}" 
                       onclick="${!isBlocked ? `selectTime('${slot}')` : ""}">
                      ${slot}
                  </div>`;
        })
        .join("");

      container.innerHTML = infoHtml + slotsHtml;
    }
  } catch (error) {
    console.error("Error loading time slots:", error);
    document.getElementById("time-slots").innerHTML =
      '<p style="text-align: center; color: var(--clr-error);">Fehler beim Laden der Termine</p>';
  }
}

function calculateEndTime(startTime, durationMinutes) {
  const [hours, minutes] = startTime.split(":").map(Number);
  const totalMinutes = hours * 60 + minutes + durationMinutes;
  const endHours = Math.floor(totalMinutes / 60);
  const endMinutes = totalMinutes % 60;
  return `${String(endHours).padStart(2, "0")}:${String(endMinutes).padStart(
    2,
    "0"
  )}`;
}

function selectTime(time) {
  // Remove previous selection
  document.querySelectorAll(".time-slot.selected").forEach((el) => {
    el.classList.remove("selected");
  });

  // Add selection
  event.target.classList.add("selected");
  bookingData.time = time;

  // Enable continue button
  document.getElementById("time-continue").disabled = false;
}

// Navigation functions
function validateStep(step) {
  if (step === 1) {
    // Validate customer data AND vehicle data
    const requiredCustomer = [
      "first_name",
      "last_name",
      "email",
      "phone",
      "address",
    ];
    const requiredVehicle = [
      "car_brand",
      "car_model",
      "car_year",
      "license_plate",
    ];
    const allRequired = [...requiredCustomer, ...requiredVehicle];
    let valid = true;

    allRequired.forEach((field) => {
      const input = document.getElementById(field);
      const error = input.nextElementSibling;

      if (!input.value.trim()) {
        input.classList.add("error");
        if (error) error.style.display = "block";
        valid = false;
      } else {
        input.classList.remove("error");
        if (error) error.style.display = "none";
        bookingData.customer[field] = input.value.trim();
      }
    });

    // Special validation for email
    const emailInput = document.getElementById("email");
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailInput.value)) {
      emailInput.classList.add("error");
      const emailError = emailInput.nextElementSibling;
      if (emailError) {
        emailError.textContent = "Bitte gültige E-Mail-Adresse eingeben";
        emailError.style.display = "block";
      }
      valid = false;
    }

    // Special validation for year
    const yearInput = document.getElementById("car_year");
    const year = parseInt(yearInput.value);
    if (year < 1950 || year > 2026 || isNaN(year)) {
      yearInput.classList.add("error");
      const yearError = yearInput.nextElementSibling;
      if (yearError) {
        yearError.textContent = "Bitte gültiges Baujahr eingeben (1950-2026)";
        yearError.style.display = "block";
      }
      valid = false;
    }

    // Check if address was calculated
    if (!bookingData.distance || bookingData.distance === 0) {
      const addressInput = document.getElementById("address");
      addressInput.classList.add("error");
      const addressError = addressInput.nextElementSibling;
      if (addressError) {
        addressError.textContent =
          "Bitte Adresse eingeben und Entfernung berechnen lassen";
        addressError.style.display = "block";
      }
      valid = false;
    }

    return valid && bookingData.distance <= 35;
  } else if (step === 2) {
    // Validate services selection
    if (bookingData.services.length === 0) {
      alert("Bitte wählen Sie mindestens einen Service aus.");
      return false;
    }

    if (bookingData.distance > 10 && bookingData.subtotal < 59.9) {
      alert(
        "Bei einer Entfernung über 10km beträgt der Mindestbestellwert 59,90€"
      );
      return false;
    }

    return true;
  } else if (step === 3) {
    return bookingData.date !== null;
  } else if (step === 4) {
    return bookingData.time !== null;
  }

  return true;
}

function nextStep() {
  if (!validateStep(currentStep)) {
    return;
  }

  // Update steps UI
  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.remove("active");
  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.add("completed");
  document.querySelector(
    `.step-panel[data-step="${currentStep}"]`
  ).style.display = "none";

  currentStep++;

  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.add("active");
  document.querySelector(
    `.step-panel[data-step="${currentStep}"]`
  ).style.display = "block";

  // Load step-specific content
  if (currentStep === 4) {
    loadTimeSlots(bookingData.date);
  } else if (currentStep === 5) {
    showSummary();
  }

  // Scroll to top
  window.scrollTo(0, 0);
}

function previousStep() {
  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.remove("active");
  document.querySelector(
    `.step-panel[data-step="${currentStep}"]`
  ).style.display = "none";

  currentStep--;

  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.remove("completed");
  document
    .querySelector(`.step[data-step="${currentStep}"]`)
    .classList.add("active");
  document.querySelector(
    `.step-panel[data-step="${currentStep}"]`
  ).style.display = "block";

  // Scroll to top
  window.scrollTo(0, 0);
}

// Show booking summary
function showSummary() {
  // Customer data
  document.getElementById("summary-customer").innerHTML = `
        <p><strong>${bookingData.customer.first_name} ${
    bookingData.customer.last_name
  }</strong></p>
        <p>${bookingData.customer.email}</p>
        <p>${bookingData.customer.phone}</p>
        <p>${bookingData.customer.address}</p>
        ${
          bookingData.customer.car_brand
            ? `<p>Fahrzeug: ${bookingData.customer.car_brand} ${
                bookingData.customer.car_model || ""
              } ${
                bookingData.customer.car_year
                  ? `(${bookingData.customer.car_year})`
                  : ""
              }</p>`
            : ""
        }
    `;

  // Services
  document.getElementById("summary-services").innerHTML =
    bookingData.services
      .map(
        (service) => `
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span>${service.name} (${service.duration_minutes} Min.)</span>
            <span>${parseFloat(service.price).toFixed(2)}€</span>
        </div>
    `
      )
      .join("") +
    `
        <hr style="margin: 1rem 0; border-color: var(--clr-surface-a30);">
        <div style="display: flex; justify-content: space-between;">
            <strong>Zwischensumme:</strong>
            <strong>${bookingData.subtotal.toFixed(2)}€</strong>
        </div>
        ${
          bookingData.travelCost > 0
            ? `
        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
            <span>Anfahrtskosten (${bookingData.distance.toFixed(1)} km):</span>
            <span>${bookingData.travelCost.toFixed(2)}€</span>
        </div>
        `
            : ""
        }
    `;

  // Appointment
  const dateParts = bookingData.date.split("-");
  const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
  const dateStr = dateObj.toLocaleDateString("de-DE", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  document.getElementById("summary-appointment").innerHTML = `
        <p><strong>Datum:</strong> ${dateStr}</p>
        <p><strong>Uhrzeit:</strong> ${bookingData.time} Uhr</p>
        <p><strong>Gesamtdauer:</strong> ${bookingData.services.reduce(
          (sum, s) => sum + parseInt(s.duration_minutes),
          0
        )} Minuten</p>
    `;

  // Total
  bookingData.total = bookingData.subtotal + bookingData.travelCost;
  document.getElementById(
    "summary-total"
  ).textContent = `${bookingData.total.toFixed(2)}€`;
}

// Confirm booking - MIT LEXWARE INTEGRATION UPDATE
async function confirmBooking() {
  try {
    // Zeige Lade-Indikator
    const confirmButton = event.target;
    const originalText = confirmButton.innerHTML;
    confirmButton.disabled = true;
    confirmButton.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Buchung wird verarbeitet...';

    const response = await fetch("api/process-booking.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(bookingData),
    });

    const result = await response.json();

    if (result.success) {
      currentAppointmentId = result.appointmentId;
      // Show confirmation
      document.querySelector(`.step[data-step="5"]`).classList.remove("active");
      document.querySelector(`.step[data-step="5"]`).classList.add("completed");
      document.querySelector(`.step-panel[data-step="5"]`).style.display =
        "none";

      document.querySelector(`.step[data-step="6"]`).classList.add("active");
      document.querySelector(`.step-panel[data-step="6"]`).style.display =
        "block";

      // Set booking number
      document.getElementById(
        "booking-number"
      ).textContent = `#${result.bookingNumber}`;

      // Fill print details
      fillPrintDetails(result.bookingNumber);

      // LEXWARE FEEDBACK - Zeige Info wenn Rechnung erstellt wurde
      if (result.lexwareCreated && result.invoiceNumber) {
        // Füge Lexware Info in die Bestätigungsseite ein
        const confirmationHeader = document.querySelector(
          '.step-panel[data-step="6"] h2'
        );
        if (confirmationHeader) {
          const lexwareInfo = document.createElement("div");
          lexwareInfo.className = "alert alert-success";
          lexwareInfo.style.marginTop = "1rem";
          lexwareInfo.innerHTML = `
            <i class="fas fa-file-invoice"></i> 
            <strong>Rechnung erstellt!</strong><br>
            Ihre Rechnung wurde automatisch in unserem System angelegt.<br>
            <small>Rechnungsnummer: ${result.invoiceNumber}</small>
          `;
          confirmationHeader.parentNode.insertBefore(
            lexwareInfo,
            confirmationHeader.nextSibling
          );
        }
        console.log("✅ Lexware Rechnung erstellt:", result.invoiceNumber);
      } else if (result.lexwareError) {
        console.warn("⚠️ Lexware Fehler:", result.lexwareError);
      }

      // Email Feedback
      if (result.emailSent) {
        console.log("✅ Bestätigungs-E-Mail gesendet");
      } else if (result.emailError) {
        console.warn("⚠️ E-Mail Fehler:", result.emailError);
      }

      // Scroll to top
      window.scrollTo(0, 0);
    } else {
      alert(
        "Ein Fehler ist aufgetreten: " +
          (result.error || "Bitte versuchen Sie es erneut.")
      );
      // Reset Button
      confirmButton.disabled = false;
      confirmButton.innerHTML = originalText;
    }
  } catch (error) {
    console.error("Error confirming booking:", error);
    alert("Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.");
    // Reset Button bei Fehler
    const confirmButton = event.target;
    if (confirmButton) {
      confirmButton.disabled = false;
      confirmButton.innerHTML = "Kostenpflichtig buchen";
    }
  }
}

// Fill print details for confirmation page
function fillPrintDetails(bookingNumber) {
  // Format data for screen display
  const formatLabel = (label) =>
    `<span style="color: var(--clr-primary-a40); font-size: 0.85rem;">${label}:</span>`;

  // Customer Details for screen
  const customerScreenHtml = `
        ${formatLabel("Name")}<br>
        <strong>${bookingData.customer.first_name} ${
    bookingData.customer.last_name
  }</strong><br><br>
        ${formatLabel("E-Mail")}<br>
        ${bookingData.customer.email}<br><br>
        ${formatLabel("Telefon")}<br>
        ${bookingData.customer.phone}<br><br>
        ${formatLabel("Adresse")}<br>
        ${bookingData.customer.address}
    `;
  document.getElementById("confirmation-customer-details").innerHTML =
    customerScreenHtml;

  // Vehicle Details for screen
  const vehicleScreenHtml = `
        ${formatLabel("Marke")}<br>
        <strong>${bookingData.customer.car_brand || "-"}</strong><br><br>
        ${formatLabel("Modell")}<br>
        ${bookingData.customer.car_model || "-"}<br><br>
        ${formatLabel("Baujahr")}<br>
        ${bookingData.customer.car_year || "-"}<br><br>
        ${formatLabel("Kennzeichen")}<br>
        <strong style="font-size: 1.1em; color: var(--clr-info);">${
          bookingData.customer.license_plate || "-"
        }</strong>
    `;
  document.getElementById("confirmation-vehicle-details").innerHTML =
    vehicleScreenHtml;

  // Appointment Details for screen
  const dateParts = bookingData.date.split("-");
  const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
  const dateStr = dateObj.toLocaleDateString("de-DE", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  const totalDuration = bookingData.services.reduce(
    (sum, s) => sum + parseInt(s.duration_minutes),
    0
  );

  const appointmentScreenHtml = `
        ${formatLabel("Datum")}<br>
        <strong>${dateStr}</strong><br><br>
        ${formatLabel("Uhrzeit")}<br>
        <strong>${bookingData.time} Uhr</strong><br><br>
        ${formatLabel("Gesamtdauer")}<br>
        ${totalDuration} Minuten<br><br>
        ${formatLabel("Entfernung")}<br>
        ${bookingData.distance.toFixed(1)} km
    `;
  document.getElementById("confirmation-appointment-details").innerHTML =
    appointmentScreenHtml;

  // Services for screen
  let servicesScreenHtml =
    '<table style="width: 100%; border-collapse: collapse;">';
  servicesScreenHtml +=
    '<thead><tr style="border-bottom: 1px solid var(--clr-surface-a30);">';
  servicesScreenHtml +=
    '<th style="padding: 0.5rem 0; text-align: left; font-size: 0.85rem; color: var(--clr-primary-a40);">Service</th>';
  servicesScreenHtml +=
    '<th style="padding: 0.5rem 0; text-align: right; font-size: 0.85rem; color: var(--clr-primary-a40);">Dauer</th>';
  servicesScreenHtml +=
    '<th style="padding: 0.5rem 0; text-align: right; font-size: 0.85rem; color: var(--clr-primary-a40);">Preis</th>';
  servicesScreenHtml += "</tr></thead><tbody>";

  bookingData.services.forEach((service) => {
    servicesScreenHtml += `
            <tr style="border-bottom: 1px solid var(--clr-surface-a20);">
                <td style="padding: 0.75rem 0;">${service.name}</td>
                <td style="padding: 0.75rem 0; text-align: right;">${
                  service.duration_minutes
                } Min.</td>
                <td style="padding: 0.75rem 0; text-align: right;">${parseFloat(
                  service.price
                ).toFixed(2)}€</td>
            </tr>
        `;
  });

  servicesScreenHtml += `
        <tr style="border-top: 1px solid var(--clr-surface-a30);">
            <td colspan="2" style="padding: 0.75rem 0;"><strong>Zwischensumme:</strong></td>
            <td style="padding: 0.75rem 0; text-align: right;"><strong>${bookingData.subtotal.toFixed(
              2
            )}€</strong></td>
        </tr>
    `;

  if (bookingData.travelCost > 0) {
    servicesScreenHtml += `
            <tr>
                <td colspan="2" style="padding: 0.75rem 0;">Anfahrtskosten (${bookingData.distance.toFixed(
                  1
                )} km):</td>
                <td style="padding: 0.75rem 0; text-align: right;">${bookingData.travelCost.toFixed(
                  2
                )}€</td>
            </tr>
        `;
  }

  servicesScreenHtml += "</tbody></table>";
  document.getElementById("confirmation-services").innerHTML =
    servicesScreenHtml;

  // Total Amount for screen
  document.getElementById(
    "confirmation-total"
  ).textContent = `${bookingData.total.toFixed(2)}€`;

  // Now fill print details (hidden elements for printing)
  const customerPrintHtml = `
        <div class="print-label">Name:</div>
        <div class="print-value">${bookingData.customer.first_name} ${bookingData.customer.last_name}</div>
        <div class="print-label">E-Mail:</div>
        <div class="print-value">${bookingData.customer.email}</div>
        <div class="print-label">Telefon:</div>
        <div class="print-value">${bookingData.customer.phone}</div>
        <div class="print-label">Adresse:</div>
        <div class="print-value">${bookingData.customer.address}</div>
    `;
  if (document.getElementById("print-customer-details")) {
    document.getElementById("print-customer-details").innerHTML =
      customerPrintHtml;
  }

  // Vehicle Details for print
  const vehiclePrintHtml = `
        <div class="print-label">Marke:</div>
        <div class="print-value">${bookingData.customer.car_brand || "-"}</div>
        <div class="print-label">Modell:</div>
        <div class="print-value">${bookingData.customer.car_model || "-"}</div>
        <div class="print-label">Baujahr:</div>
        <div class="print-value">${bookingData.customer.car_year || "-"}</div>
        <div class="print-label">Kennzeichen:</div>
        <div class="print-value" style="font-weight: bold; font-size: 1.1em;">${
          bookingData.customer.license_plate || "-"
        }</div>
    `;
  if (document.getElementById("print-vehicle-details")) {
    document.getElementById("print-vehicle-details").innerHTML =
      vehiclePrintHtml;
  }

  // Services for print
  let servicesPrintHtml = "";
  bookingData.services.forEach((service) => {
    servicesPrintHtml += `
            <tr>
                <td>${service.name}</td>
                <td style="text-align: right;">${
                  service.duration_minutes
                } Min.</td>
                <td style="text-align: right;">${parseFloat(
                  service.price
                ).toFixed(2)}€</td>
            </tr>
        `;
  });

  servicesPrintHtml += `
        <tr style="border-top: 2px solid #000;">
            <td colspan="2"><strong>Zwischensumme:</strong></td>
            <td style="text-align: right;"><strong>${bookingData.subtotal.toFixed(
              2
            )}€</strong></td>
        </tr>
    `;

  if (bookingData.travelCost > 0) {
    servicesPrintHtml += `
            <tr>
                <td colspan="2">Anfahrtskosten (${bookingData.distance.toFixed(
                  1
                )} km):</td>
                <td style="text-align: right;">${bookingData.travelCost.toFixed(
                  2
                )}€</td>
            </tr>
        `;
  }

  if (document.getElementById("print-services-body")) {
    document.getElementById("print-services-body").innerHTML =
      servicesPrintHtml;
  }

  // Total for print
  if (document.getElementById("print-total-amount")) {
    document.getElementById(
      "print-total-amount"
    ).textContent = `${bookingData.total.toFixed(2)}€`;
  }

  // Set print dates
  const currentDate = new Date().toLocaleDateString("de-DE");
  if (document.getElementById("print-date")) {
    document.getElementById("print-date").textContent = currentDate;
  }
  if (document.getElementById("print-date-footer")) {
    document.getElementById("print-date-footer").textContent = currentDate;
  }
}

// Print confirmation
function printConfirmation() {
  // Show print elements
  const printElements = document.querySelectorAll(
    ".print-header, .print-title, .print-footer"
  );
  printElements.forEach((el) => (el.style.display = "block"));

  // Trigger print
  window.print();

  // Hide print elements again after a delay
  setTimeout(() => {
    printElements.forEach((el) => (el.style.display = "none"));
  }, 500);
}

// Download as PDF (placeholder - would need server-side implementation)
function downloadPDF() {
  alert(
    'PDF-Download wird vorbereitet...\n\nHinweis: Verwenden Sie alternativ "Drucken" und wählen Sie "Als PDF speichern" im Druckdialog.'
  );
  printConfirmation();
}

// Process payment - UPDATE FÜR PAYPAL INTEGRATION
function processPayment() {
  if (currentAppointmentId) {
    // Initialisiere PayPal Payment mit der Appointment ID
    if (typeof initializePayment === "function") {
      initializePayment(currentAppointmentId);
    } else {
      console.error("PayPal payment.js nicht geladen");
      alert("Zahlungsmodul wird geladen, bitte versuchen Sie es erneut.");
    }
  } else {
    alert("Fehler: Buchungs-ID nicht gefunden. Bitte laden Sie die Seite neu.");
  }
}
