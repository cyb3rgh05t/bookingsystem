/**
 * PayPal Payment Integration für Auto-Service Buchungssystem
 * Handles payment processing and Lexware invoice creation
 */

// Globale Variablen
let appointmentId = null;
let paypalOrderId = null;

// Debug-Modus
const DEBUG_MODE = true;

// Original Konsolen-Funktion speichern
const originalConsoleError = console.error;

// PayPal-Warnungen filtern
console.error = function (...args) {
  const message = args[0]?.toString() || "";

  // Ignoriere bekannte PayPal-Warnungen
  if (
    message.includes("global_session_not_found") ||
    message.includes("paypalobjects.com")
  ) {
    if (DEBUG_MODE) {
      console.log("PayPal-Warnung ignoriert:", message);
    }
    return;
  }

  // Andere Fehler normal ausgeben
  originalConsoleError.apply(console, args);
};

/**
 * Initialisiere PayPal Button
 * Wird aufgerufen nach erfolgreicher Buchung
 */
function initializePayment(bookingId) {
  appointmentId = bookingId;

  // Lade PayPal SDK dynamisch
  loadPayPalSDK()
    .then(() => {
      renderPayPalButton();
    })
    .catch((error) => {
      console.error("PayPal SDK konnte nicht geladen werden:", error);
      // Fallback: Zeige alternativen Zahlungsbutton
      showAlternativePayment();
    });
}

/**
 * Lade PayPal SDK
 */
function loadPayPalSDK() {
  return new Promise((resolve, reject) => {
    // Prüfe ob SDK bereits geladen
    if (window.paypal) {
      resolve();
      return;
    }

    // Hole PayPal Client ID vom Server
    fetch("api/get-paypal-config.php")
      .then((response) => response.json())
      .then((config) => {
        if (!config.client_id) {
          throw new Error("PayPal nicht konfiguriert");
        }

        // Erstelle Script-Tag für PayPal SDK
        const script = document.createElement("script");
        script.src = `https://www.paypal.com/sdk/js?client-id=${config.client_id}&currency=EUR&locale=de_DE`;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      })
      .catch(reject);
  });
}

/**
 * Rendere PayPal Button
 */
function renderPayPalButton() {
  // Finde den Payment Container
  let paymentContainer = document.getElementById("payment-container");

  // Falls nicht vorhanden, erstelle ihn beim "Jetzt bezahlen" Button
  if (!paymentContainer) {
    const payButton = document.querySelector(
      '.btn-success[onclick*="processPayment"]'
    );
    if (payButton) {
      paymentContainer = document.createElement("div");
      paymentContainer.id = "payment-container";
      payButton.parentElement.appendChild(paymentContainer);
      // Verstecke den ursprünglichen Button
      payButton.style.display = "none";
    } else {
      console.error("Payment Button nicht gefunden");
      return;
    }
  }

  // Clear existing content
  paymentContainer.innerHTML = '<div id="paypal-button-container"></div>';
  paymentContainer.style.display = "block";

  // Render PayPal Buttons
  paypal
    .Buttons({
      style: {
        layout: "vertical",
        color: "blue", // Ändern von 'gold' zu 'blue'
        shape: "rect",
        label: "paypal", // Nur PayPal, keine Karten-Logos
        tagline: false,
        height: 45,
      },

      // Deaktiviere zusätzliche Funding-Optionen für Tests
      fundingSource: paypal.FUNDING.PAYPAL,

      // Erstelle Order
      createOrder: function (data, actions) {
        // Zeige Ladeindikator
        showPaymentLoading(true);

        // Erstelle PayPal Order über Backend
        return fetch("api/process-payment.php?action=create_order", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `appointment_id=${appointmentId}`,
        })
          .then((response) => {
            // DEBUG: Response als Text holen
            return response.text().then((text) => {
              console.log("=== NORMALE process-payment.php Response ===");
              console.log("Full Response:", text);
              console.log("Response Length:", text.length);
              console.log("First char code:", text.charCodeAt(0));

              // Prüfe auf BOM oder andere unsichtbare Zeichen
              if (text.charCodeAt(0) === 65279) {
                console.error("BOM detected! File has UTF-8 BOM.");
                text = text.substring(1); // Remove BOM
              }

              // Prüfe auf PHP Errors/Warnings vor JSON
              if (!text.startsWith("{")) {
                console.error("Response doesn't start with {");
                console.error("First 50 chars:", text.substring(0, 50));
              }

              try {
                const data = JSON.parse(text);
                return data;
              } catch (e) {
                console.error("JSON Parse Error:", e);
                throw new Error("Server-Antwort ist kein gültiges JSON");
              }
            });
          })
          .then((data) => {
            showPaymentLoading(false);

            if (data.success && data.order_id) {
              paypalOrderId = data.order_id;
              return data.order_id; // PayPal erwartet NUR die order_id als String!
            } else {
              throw new Error(
                data.error || "Zahlung konnte nicht initialisiert werden"
              );
            }
          })
          .catch((error) => {
            showPaymentLoading(false);
            showPaymentError(error.message);
            throw error;
          });
      },

      // Nach Genehmigung durch Kunden
      onApprove: function (data, actions) {
        showPaymentLoading(true, "Zahlung wird verarbeitet...");

        // Capture Payment über Backend
        return fetch("api/process-payment.php?action=capture_payment", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `order_id=${data.orderID}`,
        })
          .then((response) => response.json())
          .then((result) => {
            showPaymentLoading(false);

            if (result.success) {
              // Zahlung erfolgreich
              showPaymentSuccess(result);
            } else {
              throw new Error(
                result.error || "Zahlung konnte nicht abgeschlossen werden"
              );
            }
          })
          .catch((error) => {
            showPaymentLoading(false);
            showPaymentError(error.message);
          });
      },

      // Bei Abbruch
      onCancel: function (data) {
        showPaymentInfo(
          "Zahlung wurde abgebrochen. Sie können es jederzeit erneut versuchen."
        );
      },

      // Bei Fehler
      onError: function (err) {
        console.error("PayPal Error:", err);
        showPaymentError(
          "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut."
        );
      },
    })
    .render("#paypal-button-container");
}

/**
 * Alternative Zahlungsmethode (Fallback)
 */
function showAlternativePayment() {
  let container = document.getElementById("payment-container");

  if (!container) {
    const payButton = document.querySelector(
      '.btn-success[onclick*="processPayment"]'
    );
    if (payButton) {
      container = document.createElement("div");
      container.id = "payment-container";
      payButton.parentElement.appendChild(container);
    }
  }

  if (!container) return;

  container.style.display = "block";
  container.innerHTML = `
        <div class="alert alert-info">
            <h4>Zahlungsinformationen</h4>
            <p>PayPal ist momentan nicht verfügbar. Bitte nutzen Sie eine der folgenden Alternativen:</p>
            <div style="background: var(--clr-surface-a10); padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <strong>Überweisung:</strong><br>
                Kontoinhaber: Auto Service GmbH<br>
                IBAN: DE12 3456 7890 1234 5678 90<br>
                BIC: DEUTDEFF<br>
                Verwendungszweck: Buchung ${getBookingNumber()}
            </div>
            <p>Nach Zahlungseingang erhalten Sie automatisch Ihre Rechnung.</p>
        </div>
    `;
}

/**
 * Zeige Zahlungserfolg
 */
function showPaymentSuccess(result) {
  const container =
    document.getElementById("payment-container") ||
    document.querySelector("#paypal-button-container").parentElement;

  if (!container) return;

  let invoiceInfo = "";
  if (result.invoice_created && result.invoice_number) {
    invoiceInfo = `
            <div class="alert alert-success" style="margin-top: 1rem;">
                <i class="fas fa-file-invoice"></i> 
                Ihre Rechnung wurde automatisch erstellt.<br>
                <strong>Rechnungsnummer:</strong> ${result.invoice_number}
            </div>
        `;
  }

  container.innerHTML = `
        <div class="payment-success">
            <div style="text-align: center; padding: 2rem;">
                <div style="font-size: 4rem; color: var(--clr-success); margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="color: var(--clr-success); margin-bottom: 1rem;">
                    Zahlung erfolgreich!
                </h3>
                <p>Vielen Dank für Ihre Zahlung. Sie erhalten in Kürze eine Bestätigung per E-Mail.</p>
                
                ${invoiceInfo}
                
                <div style="background: var(--clr-surface-a10); padding: 1rem; border-radius: 8px; margin: 2rem 0;">
                    <small>
                        <strong>Transaktions-ID:</strong> ${
                          result.capture_id || "N/A"
                        }<br>
                        <strong>Zahlungsmethode:</strong> PayPal
                    </small>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Bestätigung drucken
                    </button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 1rem;">
                        <i class="fas fa-home"></i> Zur Startseite
                    </a>
                </div>
            </div>
        </div>
    `;

  // Update UI Status
  updateBookingStatus("paid");
}

/**
 * Zeige Fehler
 */
function showPaymentError(message) {
  let container = document.getElementById("payment-status");

  if (!container) {
    container = document.createElement("div");
    container.id = "payment-status";
    const paymentContainer = document.getElementById("payment-container");
    if (paymentContainer) {
      paymentContainer.parentElement.insertBefore(container, paymentContainer);
    }
  }

  container.style.display = "block";
  container.innerHTML = `
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Fehler:</strong> ${message}
        </div>
    `;

  setTimeout(() => {
    container.innerHTML = "";
    container.style.display = "none";
  }, 5000);
}

/**
 * Zeige Info
 */
function showPaymentInfo(message) {
  let container = document.getElementById("payment-status");

  if (!container) {
    container = document.createElement("div");
    container.id = "payment-status";
    const paymentContainer = document.getElementById("payment-container");
    if (paymentContainer) {
      paymentContainer.parentElement.insertBefore(container, paymentContainer);
    }
  }

  container.style.display = "block";
  container.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> ${message}
        </div>
    `;
}

/**
 * Zeige Ladeindikator
 */
function showPaymentLoading(show, message = "Bitte warten...") {
  let container = document.getElementById("payment-status");

  if (!container) {
    container = document.createElement("div");
    container.id = "payment-status";
    const paymentContainer = document.getElementById("payment-container");
    if (paymentContainer) {
      paymentContainer.parentElement.insertBefore(container, paymentContainer);
    }
  }

  if (show) {
    container.style.display = "block";
    container.innerHTML = `
            <div class="payment-loading">
                <div class="spinner"></div>
                <p>${message}</p>
            </div>
        `;
  } else {
    container.innerHTML = "";
    container.style.display = "none";
  }
}

/**
 * Update Booking Status in UI
 */
function updateBookingStatus(status) {
  const statusElements = document.querySelectorAll("[data-payment-status]");
  statusElements.forEach((el) => {
    if (status === "paid") {
      el.innerHTML = '<span class="badge badge-success">Bezahlt</span>';
    }
  });
}

/**
 * Hole Buchungsnummer aus UI
 */
function getBookingNumber() {
  const bookingEl = document.getElementById("booking-number");
  return bookingEl ? bookingEl.textContent.replace("#", "") : "";
}

/**
 * Process Payment - Wird vom bestehenden Button aufgerufen
 */
function processPayment() {
  // Verwende die globale currentAppointmentId wenn vorhanden
  if (typeof currentAppointmentId !== "undefined" && currentAppointmentId) {
    initializePayment(currentAppointmentId);
  } else {
    // Fallback: Versuche aus URL oder data-Attribut zu holen
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId =
      urlParams.get("id") ||
      document.querySelector("[data-appointment-id]")?.dataset.appointmentId;

    if (bookingId) {
      initializePayment(bookingId);
    } else {
      showPaymentError(
        "Buchungs-ID nicht gefunden. Bitte laden Sie die Seite neu."
      );
    }
  }
}

// CSS für Payment-Komponenten (nur wenn noch nicht vorhanden)
if (!document.getElementById("payment-styles")) {
  const paymentStyles = document.createElement("style");
  paymentStyles.id = "payment-styles";
  paymentStyles.textContent = `
        .payment-loading {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            border: 3px solid var(--clr-surface-a30);
            border-top: 3px solid var(--clr-primary-a0);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .payment-success {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #payment-status {
            margin: 1rem 0;
        }
        
        #payment-container {
            margin: 1rem 0;
        }
        
        .badge-success {
            background: var(--clr-success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .alert-error {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }
    `;
  document.head.appendChild(paymentStyles);
}
