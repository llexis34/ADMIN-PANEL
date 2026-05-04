document.addEventListener("DOMContentLoaded", () => {
  // FIX: Auth guard — verify PHP session first, fall back to sessionStorage
  (async function guardAndInit() {
    let userEmail = "";
    try {
      const authRes = await fetch("api/auth_me.php", { credentials: "include" });
      const authData = await authRes.json();
      if (!authData.ok) {
        sessionStorage.removeItem("limcoma_logged_in");
        window.location.href = "signin.html";
        return;
      }
      userEmail = authData.user?.email || "";
      sessionStorage.setItem("limcoma_logged_in", "true");
      sessionStorage.setItem("limcoma_user_email", userEmail);
    } catch (e) {
      // Network error fallback: trust sessionStorage if set
      if (sessionStorage.getItem("limcoma_logged_in") !== "true") {
        window.location.href = "signin.html";
        return;
      }
      userEmail = sessionStorage.getItem("limcoma_user_email") || "";
    }

    initPage(userEmail);

    document.querySelectorAll("input[type='text'], textarea").forEach(input => {
      input.addEventListener("input", function () {
        this.value = this.value.toUpperCase();
      });
    });
  })();
});

function initPage(resolvedEmail) {
  const $id = (id) => document.getElementById(id);

  const form = $id("membershipForm");
  const submitBtn = $id("submitBtn");
  const submitMsg = $id("submitMsg");

  const autoEmail = $id("autoEmail");
  if (autoEmail && resolvedEmail) autoEmail.value = resolvedEmail;

  const photoInput = $id("photoInput");
  const photoPreview = $id("photoPreview");

  const signatureInput = $id("signatureInput");
  const signaturePreview = $id("signaturePreview");
  const signatureDateEl = $id("signatureDate");
  const signatureWrap = $id("signatureWrap");

  const confirmDetails = $id("confirmDetails");
  const confirmSubmitBtn = $id("confirmSubmitBtn");
  const missingFieldsList = $id("missingFieldsList");

  const logoutBtn = $id("logoutBtn");
  const confirmLogoutBtn = $id("confirmLogoutBtn");

  const formLockedArea = $id("formLockedArea");
  const openKasunduanBtn = $id("openKasunduanBtn");

  const subscriptionAgreementStatus = $id("subscriptionAgreementStatus");
  const kasunduanStatus = $id("kasunduanStatus");

  const subscriptionAgreementModalId = "#subscriptionAgreementModal";
  const subscriptionAgreementContent = $id("subscriptionAgreementContent");
  const subscriptionAgreementScroll = $id("subscriptionAgreementScroll");
  const subscriptionAgreementCheck = $id("subscriptionAgreementCheck");
  const subscriptionAgreeBtn = $id("subscriptionAgreeBtn");

  const kasunduanModalId = "#kasunduanModal";
  const kasunduanContent = $id("kasunduanContent");
  const kasunduanScroll = $id("kasunduanScroll");
  const kasunduanCheck = $id("kasunduanCheck");
  const kasunduanAgreeBtn = $id("kasunduanAgreeBtn");

  const applicationTypeInputs = form?.querySelectorAll('input[name="application_type"]') || [];

  const modal = (id, action) => {
    if (window.jQuery) window.jQuery(id).modal(action);
  };

  form?.addEventListener("submit", (e) => {
    e.preventDefault();
    e.stopPropagation();
    return false;
  });

  const getLocalISODate = () => {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
  };

  const lockSignatureDate = () => {
    if (!signatureDateEl) return;

    const today = getLocalISODate();
    signatureDateEl.value = today;
    signatureDateEl.setAttribute("readonly", "readonly");
    signatureDateEl.setAttribute("tabindex", "-1");
    signatureDateEl.classList.add("locked-date");

    const hardLock = (e) => {
      if (e) e.preventDefault();
      signatureDateEl.value = today;
      return false;
    };

    signatureDateEl.addEventListener("keydown", hardLock);
    signatureDateEl.addEventListener("paste", hardLock);
    signatureDateEl.addEventListener("input", hardLock);
    signatureDateEl.addEventListener("change", hardLock);
    signatureDateEl.addEventListener("click", hardLock);
  };

  lockSignatureDate();

  const esc = (s) =>
    String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const labelMap = {
    application_type: "Application Type",
    last_name: "Last Name",
    first_name: "First Name",
    middle_name: "Middle Name",
    home_address: "Tirahan / Home Address",
    gender: "Kasarian / Gender",
    religion: "Relihiyon / Religion",
    birthdate: "Kapanganakan / Birthdate",
    age: "Edad / Age",
    dependents: "Bilang ng dependents",
    civil_status: "Katayuang Sibil / Civil Status",
    education: "Educational Attainment",
    livelihood: "Hanapbuhay / Source of Income",
    gross_monthly_income: "Gross Monthly Income",
    telephone: "Telepono",
    mobile: "Mobile No.",
    email: "Email Address",
    facebook_link: "Facebook Profile Link",
    tin: "TIN",
    work_address: "Lugar ng Hanapbuhay / Work Address",
    ofw_country: "OFW Country",
    ofw_work: "OFW Work",
    ofw_years: "Years Working Abroad",
    spouse_name: "Spouse Name",
    spouse_occupation: "Spouse Occupation",
    spouse_company: "Spouse Company",
    father_name: "Father's Name",
    father_occupation: "Father's Occupation",
    mother_name: "Mother's Name",
    mother_occupation: "Mother's Occupation",
    benef_name_1: "Beneficiary 1 Name",
    benef_relation_1: "Beneficiary 1 Relation",
    benef_alloc_1: "Beneficiary 1 % Allocation",
    benef_contact_1: "Beneficiary 1 Contact Number",
    benef_name_2: "Beneficiary 2 Name",
    benef_relation_2: "Beneficiary 2 Relation",
    benef_alloc_2: "Beneficiary 2 % Allocation",
    benef_contact_2: "Beneficiary 2 Contact Number",
    benef_name_3: "Beneficiary 3 Name",
    benef_relation_3: "Beneficiary 3 Relation",
    benef_alloc_3: "Beneficiary 3 % Allocation",
    benef_contact_3: "Beneficiary 3 Contact Number",
    benef_name_4: "Beneficiary 4 Name",
    benef_relation_4: "Beneficiary 4 Relation",
    benef_alloc_4: "Beneficiary 4 % Allocation",
    benef_contact_4: "Beneficiary 4 Contact Number",
    using_feeds_now: "Using Feeds Now",
    feeds_brand: "Feeds Brand",
    baboy_sow: "Baboy - Sow",
    baboy_piglet: "Baboy - Piglet",
    baboy_boar: "Baboy - Boar",
    baboy_grower: "Baboy - Grower",
    manok_patilugin: "Manok - Paitlugin",
    manok_broiler: "Manok - Broiler",
    iba_pang_alaga: "Iba Pang Alaga",
    signature_file: "Signature Upload",
    signature_date: "Signature Date",
    photoInput: "2x2 Photo",
    signatureInput: "Signature Upload",
    products: "Mga Produkto / Serbisyo"
  };

  const state = {
    subscriptionAccepted: false,
    kasunduanAccepted: false,
    currentApplicationType: "",
    subscriptionPromptedFor: ""
  };

  const subscriptionAgreementTemplates = {
    Associate: `
      <div class="agreement-topline">
        <div class="agreement-chip">FOR NEW ASSOCIATE MEMBER</div>
        <div class="agreement-chip">MRD-12-A/Rev. 1</div>
      </div>

      <h6>SUBSCRIPTION AGREEMENT</h6>

      <p>
        I, ________________________, single / married, of legal age, a resident of
        ________________________________, hereby subscribe preferred shares of the authorized
        share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered and existing
        under and by virtue of the laws of the Republic of the Philippines, with principal office address at
        Gen. Luna St., Sabang, Lipa City.
      </p>

      <p>In view of the foregoing, I hereby pledge to:</p>

      <ol type="a">
        <li>
          Subscribe <strong>THREE HUNDRED (300)</strong> preferred shares with the total amount of
          <strong>THREE THOUSAND PESOS (P 3,000.00)</strong>;
        </li>
        <li>
          Pay the sum of at least <strong>ONE THOUSAND PESOS (P1,000.00)</strong> representing the value of
          <strong>ONE HUNDRED (100)</strong> shares, upon approval of my application for membership.
        </li>
        <li>
          Pay my remaining subscribed capital of <strong>TWO THOUSAND PESOS (P2,000.00)</strong>
          within <strong>TWO (2) years</strong>.
        </li>
      </ol>

      <p>
        I understand that my failure to pay the full subscription on the terms stated above may affect my rights
        and the status of my membership in accordance with the Cooperative By-Laws and its rules and regulations.
      </p>

      <div class="signature-lines">
        <div class="line-row">
          <div class="line-box">
            Done this <span class="line"></span>
          </div>
          <div class="line-box">
            at <span class="line"></span>
          </div>
        </div>

        <div class="line-row">
          <div class="line-box">
            <span class="line"></span>
            <div>MRD Manager</div>
          </div>
          <div class="line-box">
            <span class="line"></span>
            <div>Name and Signature of Subscriber</div>
          </div>
        </div>
      </div>
    `,

    Regular: `
      <div class="agreement-topline">
        <div class="agreement-chip">FOR TRANSFER TO REGULAR MEMBER</div>
        <div class="agreement-chip">MRD-12-B/Rev. 1</div>
      </div>

      <h6>SUBSCRIPTION AGREEMENT</h6>

      <p>
        I, ________________________, single / married, of legal age, a resident of
        ________________________________, hereby subscribe common shares of the authorized
        share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered and existing
        under and by virtue of the laws of the Republic of the Philippines, with principal office address at
        Gen. Luna St., Sabang, Lipa City.
      </p>

      <p>In view of the foregoing, I hereby pledge to:</p>

      <ol type="a">
        <li>
          Subscribe <strong>TWO THOUSAND (2,000)</strong> common shares with the total amount of
          <strong>TWENTY THOUSAND PESOS (P 20,000.00)</strong>;
        </li>
        <li>
          Pay the required minimum share amounting to <strong>TEN THOUSAND PESOS (P10,000.00)</strong>
          representing the value of <strong>ONE THOUSAND (1,000)</strong> shares, upon approval of my application for membership.
        </li>
        <li>
          Pay my remaining subscribed capital of <strong>TEN THOUSAND PESOS (P10,000.00)</strong>
          within <strong>FIVE (5) years</strong>.
        </li>
      </ol>

      <p>
        I understand that my failure to pay the full subscription on the terms stated above may affect my rights
        and the status of my membership in accordance with the Cooperative By-Laws and its rules and regulations.
      </p>

      <div class="signature-lines">
        <div class="line-row">
          <div class="line-box">
            Done this <span class="line"></span>
          </div>
          <div class="line-box">
            at <span class="line"></span>
          </div>
        </div>

        <div class="line-row">
          <div class="line-box">
            <span class="line"></span>
            <div>MRD Manager</div>
          </div>
          <div class="line-box">
            <span class="line"></span>
            <div>Name and Signature of Subscriber</div>
          </div>
        </div>
      </div>
    `,

    "Transfer to Regular": `
      <div class="agreement-topline">
        <div class="agreement-chip">FOR TRANSFER TO REGULAR MEMBER</div>
        <div class="agreement-chip">MRD-12-B/Rev. 1</div>
      </div>

      <h6>SUBSCRIPTION AGREEMENT</h6>

      <p>
        I, ________________________, single / married, of legal age, a resident of
        ________________________________, hereby subscribe common shares of the authorized
        share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered and existing
        under and by virtue of the laws of the Republic of the Philippines, with principal office address at
        Gen. Luna St., Sabang, Lipa City.
      </p>

      <p>In view of the foregoing, I hereby pledge to:</p>

      <ol type="a">
        <li>
          Subscribe <strong>TWO THOUSAND (2,000)</strong> common shares with the total amount of
          <strong>TWENTY THOUSAND PESOS (P 20,000.00)</strong>;
        </li>
        <li>
          Pay the required minimum share amounting to <strong>TEN THOUSAND PESOS (P10,000.00)</strong>
          representing the value of <strong>ONE THOUSAND (1,000)</strong> shares, upon approval of my application for membership.
        </li>
        <li>
          Pay my remaining subscribed capital of <strong>TEN THOUSAND PESOS (P10,000.00)</strong>
          within <strong>FIVE (5) years</strong>.
        </li>
      </ol>

      <p>
        I understand that my failure to pay the full subscription on the terms stated above may affect my rights
        and the status of my membership in accordance with the Cooperative By-Laws and its rules and regulations.
      </p>

      <div class="signature-lines">
        <div class="line-row">
          <div class="line-box">
            Done this <span class="line"></span>
          </div>
          <div class="line-box">
            at <span class="line"></span>
          </div>
        </div>

        <div class="line-row">
          <div class="line-box">
            <span class="line"></span>
            <div>MRD Manager</div>
          </div>
          <div class="line-box">
            <span class="line"></span>
            <div>Name and Signature of Subscriber</div>
          </div>
        </div>
      </div>
    `
  };

  const kasunduanTemplate = `
    <h6>KASUNDUAN, PAGSAPI AT SUBSKRIPSYON SA KAPITAL</h6>

    <p>
      Ako ay sumasang-ayon na maging kasapi ng Limcoma Multi-Purpose Cooperative at handang dumalo sa kaukulang pag-aaral o ang tinatawag na
      <strong>“Pre-Membership Education Seminar”</strong> upang malaman ko ang lahat ng mga layunin at mga gawaing pangkabuhayan ng kooperatibang ito.
    </p>

    <p>
      Pagkatapos na ako’y matanggap bilang kasapi ng kooperatibang ito ay nangangako ako na susunod sa mga naririto'ng patakaran at alituntunin.
    </p>

    <ol>
      <li>
        Ako ay nangangakong susunod o tutupad sa mga tadhana ng Artikulo ng Kooperatiba, “By Laws” at lahat ng kautusan, patakaran o alituntunin na
        ipinatutupad ng kooperatiba sa mga kasapi at iba pang mga kinikilalang awtoridad at kung ako’y magkakasala o magkulang sa pagsunod ay nalalaman ko
        po na ako’y mapaparusahan ng alinman sa mga sumusunod:
        <ul>
          <li>Multa</li>
          <li>Pagkasuspindi sa kooperatiba</li>
          <li>Pagkatiwalag sa kooperatiba</li>
        </ul>
      </li>

      <li>
        Ako ay nangangakong dadalo sa lahat ng pagpupulong ng kooperatiba, kumperensiya man o seminar lalung-lalo na sa
        <strong>“Taunang Pangkalahatang Pagpupulong”</strong> o ang <strong>“Annual Regular General Assembly Meeting”</strong>
        para sa mga regular na kasapi at kung hindi makakadalo dahil sa hindi maiwasang kadahilanan ay nararapat na may kapahintulutan ng kinauukulang pinuno.
      </li>

      <li>
        Na ako ay maaaring matanggal bilang kasapi sa mga sumusunod na kadahilanan:
        <ul>
          <li>Hindi tumatangkilik ng mga produktong kooperatiba sa loob ng dalawang (2) taon.</li>
          <li>May pagkakataong na lampas sa isang (1) taon.</li>
          <li>Kahit padalhan ng sulat ay hindi tumutugon sa kahit na anong kadahilanan.</li>
        </ul>
      </li>

      <li>
        Na ako ay susunod sa kautusan ng mga kinikilalang awtoridad tulad ng Cooperative Development Authority (CDA) para sa aming kabutihan.
      </li>

      <li>
        Na ipinangangako ko na ako’y magiging isang mabuting kasapi ng kooperatiba at kung kinakailangan ng samahan ang aking tulong ay ako’y nakahandang magbigay ng personal na serbisyo para sa ikaunlad nito.
      </li>

      <li>
        Na ako ay makikibahagi sa patuloy na pagpapalago ng kapital ng kooperatiba sa pamamagitan ng paglalaan ng aking taunang dibidendo bilang karagdagang subskripsyon at saping kapital.
      </li>

      <li>
        Na batid ko at sumang-ayon ako na ang saping kapital ay hindi maaaring bawasan o bawiin sa loob ng 1 taon mula ng ito ay malagak maliban na lamang kung may pahintulot ng pamunuan ng Hunta Direktiba.
      </li>

      <li>
        Na nalaman ko na kung ako’y magkasala sa kooperatiba at tuluyang itiwaalag ay maaaring parusahan ako ng samahan na hindi na ibalik sa akin ang lahat kong karapatan, kapakinabangan o ari-arian na nasa pag-iingat ng kooperatiba, maging ito ay salapi o anupaman depende sa bigat ng aking pagkakasala.
      </li>
    </ol>

    <h6 style="margin-top:24px;">DEKLARASYON AT PAHINTULOT SA PAGKOLEKTA AT PAGPROSESO NG PERSONAL NA IMPORMASYON</h6>

    <p>
      Pinatutunayan ko na lahat ng mga impormasyon sa dokumentong ito ay totoo. Batid ko na anumang pagsisinungaling o pagkakamali ay magiging batayan sa
      pagkawalang-bisa, pagkansela ng aking aplikasyon o pagkatiwalag sa pagiging kasapi at handa kong tanggapin ang anumang kaparusahang naaayon sa batas ng
      Limcoma Multi-Purpose Cooperative.
    </p>

    <p>
      Sa pamamagitan ng aking paglagda sa ibaba, sumasang-ayon ako sa ipinatutupad na Data Privacy Act at nagbibigay ng aking pahintulot na kolektahin at
      iproseso ang aking personal na impormasyon alinsunod dito.
    </p>

    <div class="signature-lines">
      <div class="line-row">
        <div class="line-box" style="max-width:340px; margin-left:auto;">
          <span class="line"></span>
          <div>Lagda at Petsa</div>
        </div>
      </div>
    </div>
  `;

  const previewImage = (inputEl, previewEl, invalidContainerSelector) => {
    if (!inputEl || !previewEl) return;

    inputEl.addEventListener("change", () => {
      const file = inputEl.files?.[0];
      if (!file) return;

      previewEl.src = URL.createObjectURL(file);
      previewEl.style.display = "block";

      const container = inputEl.closest(invalidContainerSelector);
      if (container) container.classList.remove("invalid");
    });
  };

  previewImage(photoInput, photoPreview, ".photo-box");
  previewImage(signatureInput, signaturePreview, ".signature-upload-wrap");

  logoutBtn?.addEventListener("click", () => {
    modal("#logoutConfirmModal", "show");
  });

  confirmLogoutBtn?.addEventListener("click", async () => {
    // FIX: Call PHP logout endpoint to destroy server-side session
    try {
      await fetch("api/auth_logout.php", { method: "POST", credentials: "include" });
    } catch (e) { /* ignore network errors during logout */ }
    sessionStorage.removeItem("limcoma_logged_in");
    sessionStorage.removeItem("limcoma_user_email");
    modal("#logoutConfirmModal", "hide");
    window.location.href = "signin.html";
  });

  const setControlsDisabled = (selector, disabled, exceptIds = []) => {
    const nodes = form?.querySelectorAll(selector) || [];
    nodes.forEach((el) => {
      if (!el.id) {
        if (disabled) el.setAttribute("disabled", "disabled");
        else el.removeAttribute("disabled");
        return;
      }

      if (exceptIds.includes(el.id)) return;
      if (disabled) el.setAttribute("disabled", "disabled");
      else el.removeAttribute("disabled");
    });
  };

  const setSubscriptionAcceptedUI = (accepted) => {
    const statusText = subscriptionAgreementStatus?.querySelector(".status-text");
    if (subscriptionAgreementStatus && statusText) {
      if (accepted) {
        subscriptionAgreementStatus.classList.add("accepted");
        statusText.textContent = "Accepted";
      } else {
        subscriptionAgreementStatus.classList.remove("accepted");
        statusText.textContent = "Not yet accepted";
      }
    }
  };

  const setKasunduanAcceptedUI = (accepted) => {
    const statusText = kasunduanStatus?.querySelector(".status-text");
    if (kasunduanStatus && statusText) {
      if (accepted) {
        kasunduanStatus.classList.add("accepted");
        statusText.textContent = "Accepted";
      } else {
        kasunduanStatus.classList.remove("accepted");
        statusText.textContent = "Not yet accepted";
      }
    }
  };

  const updateLocks = () => {
    const subscriptionOk = state.subscriptionAccepted;
    const kasunduanOk = state.kasunduanAccepted;

    if (formLockedArea) {
      if (subscriptionOk) formLockedArea.classList.remove("form-locked");
      else formLockedArea.classList.add("form-locked");
    }

    const allLockedFieldsSelector = `
      input.field, input.photo-input, textarea, select,
      .field, .photo-input
    `;

    if (!subscriptionOk) {
      setControlsDisabled(allLockedFieldsSelector, true, ["photoInput"]);
      setControlsDisabled('input[type="radio"][name="application_type"]', false);
      setControlsDisabled('input[name="code_no_inside"]', false);
      if (photoInput) photoInput.removeAttribute("disabled");
    } else {
      setControlsDisabled(allLockedFieldsSelector, false);
      setControlsDisabled('input[type="radio"][name="application_type"]', false);

      if (!kasunduanOk) {
        if (signatureInput) signatureInput.setAttribute("disabled", "disabled");
        if (signatureWrap) signatureWrap.classList.add("locked-signature");
        if (submitBtn) submitBtn.setAttribute("disabled", "disabled");
      } else {
        if (signatureInput) signatureInput.removeAttribute("disabled");
        if (signatureWrap) signatureWrap.classList.remove("locked-signature");
        if (submitBtn) submitBtn.removeAttribute("disabled");
      }
    }

    if (!kasunduanOk) {
      if (signatureInput) signatureInput.setAttribute("disabled", "disabled");
      if (signatureWrap) signatureWrap.classList.add("locked-signature");
      if (submitBtn) submitBtn.setAttribute("disabled", "disabled");
    }

    if (signatureDateEl) {
      signatureDateEl.removeAttribute("disabled");
      signatureDateEl.classList.add("locked-date");
    }
  };

  const resetSubscriptionAgreementModalState = () => {
    if (subscriptionAgreementScroll) subscriptionAgreementScroll.scrollTop = 0;
    if (subscriptionAgreementCheck) {
      subscriptionAgreementCheck.checked = false;
      subscriptionAgreementCheck.disabled = true;
    }
    if (subscriptionAgreeBtn) subscriptionAgreeBtn.disabled = true;
  };

  const resetKasunduanModalState = () => {
    if (kasunduanScroll) kasunduanScroll.scrollTop = 0;
    if (kasunduanCheck) {
      kasunduanCheck.checked = false;
      kasunduanCheck.disabled = true;
    }
    if (kasunduanAgreeBtn) kasunduanAgreeBtn.disabled = true;
  };

  const enableCheckWhenScrolledToBottom = (scrollEl, checkboxEl) => {
    if (!scrollEl || !checkboxEl) return;

    const reachedBottom =
      Math.ceil(scrollEl.scrollTop + scrollEl.clientHeight) >= scrollEl.scrollHeight - 2;

    if (reachedBottom) {
      checkboxEl.disabled = false;
    }
  };

  subscriptionAgreementScroll?.addEventListener("scroll", () => {
    enableCheckWhenScrolledToBottom(subscriptionAgreementScroll, subscriptionAgreementCheck);
  });

  kasunduanScroll?.addEventListener("scroll", () => {
    enableCheckWhenScrolledToBottom(kasunduanScroll, kasunduanCheck);
  });

  subscriptionAgreementCheck?.addEventListener("change", () => {
    if (subscriptionAgreeBtn) subscriptionAgreeBtn.disabled = !subscriptionAgreementCheck.checked;
  });

  kasunduanCheck?.addEventListener("change", () => {
    if (kasunduanAgreeBtn) kasunduanAgreeBtn.disabled = !kasunduanCheck.checked;
  });

  const showSubscriptionAgreementForType = (appType) => {
    if (!appType || !subscriptionAgreementContent) return;
    subscriptionAgreementContent.innerHTML =
      subscriptionAgreementTemplates[appType] || subscriptionAgreementTemplates.Associate;
    resetSubscriptionAgreementModalState();
    modal(subscriptionAgreementModalId, "show");
  };

  const showKasunduanAgreement = () => {
    if (!kasunduanContent) return;
    kasunduanContent.innerHTML = kasunduanTemplate;
    resetKasunduanModalState();
    modal(kasunduanModalId, "show");
  };

  const getSelectedApplicationType = () => {
    const selected = form?.querySelector('input[name="application_type"]:checked');
    return selected ? selected.value : "";
  };

  applicationTypeInputs.forEach((radio) => {
    radio.addEventListener("change", () => {
      const selectedType = getSelectedApplicationType();
      if (!selectedType) return;

      if (selectedType !== state.currentApplicationType) {
        state.currentApplicationType = selectedType;
        state.subscriptionAccepted = false;
        setSubscriptionAcceptedUI(false);
        updateLocks();

        state.subscriptionPromptedFor = selectedType;
        showSubscriptionAgreementForType(selectedType);
      }
    });
  });

  subscriptionAgreeBtn?.addEventListener("click", () => {
    state.subscriptionAccepted = true;
    setSubscriptionAcceptedUI(true);
    updateLocks();
    modal(subscriptionAgreementModalId, "hide");
  });

  kasunduanAgreeBtn?.addEventListener("click", () => {
    state.kasunduanAccepted = true;
    setKasunduanAcceptedUI(true);
    updateLocks();
    modal(kasunduanModalId, "hide");
  });

  openKasunduanBtn?.addEventListener("click", () => {
    showKasunduanAgreement();
  });

  signatureInput?.addEventListener("click", (e) => {
    if (!state.subscriptionAccepted) {
      e.preventDefault();
      const selectedType = getSelectedApplicationType();
      if (selectedType) showSubscriptionAgreementForType(selectedType);
      return false;
    }

    if (!state.kasunduanAccepted) {
      e.preventDefault();
      showKasunduanAgreement();
      return false;
    }
  });

  signatureWrap?.addEventListener("click", (e) => {
    if (!state.subscriptionAccepted) {
      e.preventDefault();
      const selectedType = getSelectedApplicationType();
      if (selectedType) showSubscriptionAgreementForType(selectedType);
      return false;
    }

    if (!state.kasunduanAccepted) {
      e.preventDefault();
      showKasunduanAgreement();
      return false;
    }
  });

  const clearHighlights = () => {
    if (!form) return;

    form.querySelectorAll(".field.invalid").forEach((el) => el.classList.remove("invalid"));
    form.querySelectorAll(".checks.invalid-group").forEach((el) => el.classList.remove("invalid-group"));
    form.querySelectorAll(".required-check-group.invalid-group").forEach((el) => el.classList.remove("invalid-group"));
    form.querySelectorAll(".inline-q.invalid-group").forEach((el) => el.classList.remove("invalid-group"));
    form.querySelectorAll(".photo-box.invalid").forEach((el) => el.classList.remove("invalid"));
    form.querySelectorAll(".signature-upload-wrap.invalid").forEach((el) => el.classList.remove("invalid"));
  };

  const scrollToEl = (el) => {
    if (!el) return;
    el.scrollIntoView({ behavior: "smooth", block: "center" });
  };

  const isBlank = (value) => !String(value || "").trim();

  const validateFieldByType = (el) => {
    const type = (el.getAttribute("type") || "").toLowerCase();
    const value = String(el.value || "").trim();

    if (type === "number") return value !== "";
    if (type === "email") return value !== "" && el.checkValidity();
    if (type === "url") return value !== "" && el.checkValidity();
    if (type === "date") return value !== "";

    return value !== "";
  };

  const validateRequiredFields = () => {
    if (!form) return { missingLabels: [], firstBadEl: null };

    clearHighlights();

    const missingLabels = [];
    let firstBadEl = null;
    const radiosDone = new Set();

    form.querySelectorAll("[required]").forEach((el) => {
      if (el.name && el.name.startsWith("benef_")) return;

      const type = (el.getAttribute("type") || "").toLowerCase();
      const name = el.getAttribute("name") || "";
      const id = el.id || "";

      if (type === "radio" && name) {
        if (radiosDone.has(name)) return;
        radiosDone.add(name);

        const checked = form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`);

        if (!checked) {
          missingLabels.push(labelMap[name] || name);
          const anyRadio = form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]`);
          const group = anyRadio?.closest(".checks") || anyRadio?.closest(".inline-q");
          if (group) group.classList.add("invalid-group");
          if (!firstBadEl) firstBadEl = group || anyRadio;
        }
        return;
      }

      if (type === "file") {
        if (!el.files?.length) {
          missingLabels.push(labelMap[id] || labelMap[name] || name || "File Upload");

          if (id === "photoInput") {
            const pbox = el.closest(".photo-box");
            if (pbox) pbox.classList.add("invalid");
            if (!firstBadEl) firstBadEl = pbox || el;
          }

          if (id === "signatureInput") {
            const swrap = el.closest(".signature-upload-wrap");
            if (swrap) swrap.classList.add("invalid");
            if (!firstBadEl) firstBadEl = swrap || el;
          }
        }
        return;
      }

      if (!validateFieldByType(el)) {
        missingLabels.push(labelMap[name] || name || el.tagName);
        if (el.classList?.contains("field")) el.classList.add("invalid");
        if (!firstBadEl) firstBadEl = el;
      }
    });

    const productGroup = form.querySelector('[data-required-check-group="products"]');
    const productChecks = form.querySelectorAll('input[type="checkbox"][name^="avail_"]');
    const hasSelectedProduct = [...productChecks].some((cb) => cb.checked);

    if (!hasSelectedProduct) {
      missingLabels.push(labelMap.products);
      if (productGroup) productGroup.classList.add("invalid-group");
      if (!firstBadEl) firstBadEl = productGroup || productChecks[0];
    }

    if (photoInput?.hasAttribute("required") && !photoInput.files?.length) {
      if (!missingLabels.includes(labelMap.photoInput)) missingLabels.push(labelMap.photoInput);
      const pbox = photoInput.closest(".photo-box");
      if (pbox) pbox.classList.add("invalid");
      if (!firstBadEl) firstBadEl = pbox || photoInput;
    }

    if (signatureInput?.hasAttribute("required") && !signatureInput.files?.length) {
      if (!missingLabels.includes(labelMap.signatureInput)) missingLabels.push(labelMap.signatureInput);
      const swrap = signatureInput.closest(".signature-upload-wrap");
      if (swrap) swrap.classList.add("invalid");
      if (!firstBadEl) firstBadEl = swrap || signatureInput;
    }

    const uniqueLabels = [...new Set(missingLabels)];
    return { missingLabels: uniqueLabels, firstBadEl };
  };

  const attachLiveFixListeners = () => {
    if (!form) return;

    form.addEventListener("input", (e) => {
      const el = e.target;
      if (!el) return;

      if (el.classList?.contains("field")) {
        const type = (el.getAttribute("type") || "").toLowerCase();
        const value = String(el.value || "").trim();

        if (type === "email" || type === "url") {
          if (value && el.checkValidity()) el.classList.remove("invalid");
        } else if (!isBlank(value)) {
          el.classList.remove("invalid");
        }
      }
    });

    form.addEventListener("change", (e) => {
      const el = e.target;
      if (!el) return;

      const type = (el.getAttribute?.("type") || "").toLowerCase();

      if (type === "radio") {
        const group = el.closest(".checks") || el.closest(".inline-q");
        if (group) group.classList.remove("invalid-group");
      }

      if (type === "checkbox" && el.name && el.name.startsWith("avail_")) {
        const productGroup = form.querySelector('[data-required-check-group="products"]');
        const productChecks = form.querySelectorAll('input[type="checkbox"][name^="avail_"]');
        const hasSelectedProduct = [...productChecks].some((cb) => cb.checked);
        if (hasSelectedProduct && productGroup) {
          productGroup.classList.remove("invalid-group");
        }
      }

      if (type === "file") {
        if (el.id === "photoInput") {
          const pbox = el.closest(".photo-box");
          if (pbox && el.files?.length) pbox.classList.remove("invalid");
        }

        if (el.id === "signatureInput") {
          const swrap = el.closest(".signature-upload-wrap");
          if (swrap && el.files?.length) swrap.classList.remove("invalid");
        }
      }
    });
  };

  attachLiveFixListeners();

  const setupChecklistUploads = () => {
    const uploadInputs = document.querySelectorAll(".checklist-upload-input");

    uploadInputs.forEach((input) => {
      input.addEventListener("change", () => {
        const targetId = input.dataset.checkTarget;
        const targetCheck = targetId ? document.getElementById(targetId) : null;
        const fileNameEl = document.getElementById(`${input.id}_name`);
        const wrapper = input.closest(".checklist-item");
        const hasFile = !!input.files?.length;
        const fileName = hasFile ? input.files[0].name : "No file selected";

        if (fileNameEl) fileNameEl.textContent = fileName;
        if (targetCheck) targetCheck.checked = hasFile;

        if (wrapper) {
          if (hasFile) wrapper.classList.add("is-uploaded");
          else wrapper.classList.remove("is-uploaded");
        }
      });
    });
  };

  setupChecklistUploads();

  const collectChecklistUploads = () => {
    const rows = [];
    const items = document.querySelectorAll(".checklist-upload-input");

    items.forEach((input) => {
      const targetId = input.dataset.checkTarget;
      const check = targetId ? document.getElementById(targetId) : null;
      const label = check
        ? document.querySelector(`label[for="${check.id}"]`)?.innerText.trim()
        : input.id;

      if (input.files?.length) {
        rows.push({
          label: `Checklist: ${label || input.id}`,
          value: input.files[0].name
        });
      }
    });

    return rows;
  };

  const collectFormData = () => {
    if (!form) return [];

    const rows = [];
    const handled = new Set();

    form.querySelectorAll("input[name], textarea[name], select[name]").forEach((el) => {
      const name = el.getAttribute("name");
      if (!name || handled.has(name)) return;

      const type = (el.getAttribute("type") || "").toLowerCase();

      if (type === "radio") {
        handled.add(name);
        const checked = form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`);
        rows.push({
          label: labelMap[name] || name,
          value: checked ? checked.value : "(not selected)"
        });
        return;
      }

      if (type === "checkbox") {
        if (name.startsWith("avail_")) return;
        handled.add(name);
        rows.push({
          label: labelMap[name] || name,
          value: el.checked ? "Yes" : "No"
        });
        return;
      }

      if (type === "file") {
        handled.add(name);
        rows.push({
          label: labelMap[name] || name,
          value: el.files?.[0]?.name || "(no file selected)"
        });
        return;
      }

      handled.add(name);
      const v = String(el.value || "").trim();
      rows.push({
        label: labelMap[name] || name,
        value: v || "(empty)"
      });
    });

    const selectedProducts = [];
    if (form.querySelector('input[name="avail_feeds"]')?.checked) selectedProducts.push("Feeds");
    if (form.querySelector('input[name="avail_loans"]')?.checked) selectedProducts.push("Loans");
    if (form.querySelector('input[name="avail_savings"]')?.checked) selectedProducts.push("Savings");
    if (form.querySelector('input[name="avail_time_deposit"]')?.checked) selectedProducts.push("Time Deposit");

    rows.push({
      label: "Mga Produkto / Serbisyo",
      value: selectedProducts.length ? selectedProducts.join(", ") : "(none)"
    });

    if (photoInput) {
      rows.push({
        label: "2x2 Photo",
        value: photoInput.files?.[0]?.name || "(no photo selected)"
      });
    }

    if (signatureInput) {
      rows.push({
        label: "Signature Upload",
        value: signatureInput.files?.[0]?.name || "(no signature uploaded)"
      });
    }

    rows.push({
      label: "Subscription Agreement",
      value: state.subscriptionAccepted ? "Accepted" : "Not accepted"
    });

    rows.push({
      label: "Kasunduan / Capital Agreement",
      value: state.kasunduanAccepted ? "Accepted" : "Not accepted"
    });

    const checklistRows = collectChecklistUploads();
    if (checklistRows.length) rows.push(...checklistRows);

    return rows;
  };

  const renderSummary = () => {
    const rows = collectFormData();
    if (!confirmDetails) return;

    confirmDetails.innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm table-bordered" style="background:#fff;">
          <thead>
            <tr style="background:#f3f4f6;">
              <th style="width:40%; font-weight:900;">Field</th>
              <th style="font-weight:900;">Value</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map((r) => `
              <tr>
                <td style="font-weight:800;">${esc(r.label)}</td>
                <td>${esc(r.value)}</td>
              </tr>
            `).join("")}
          </tbody>
        </table>
      </div>
    `;
  };

  submitBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();

    const selectedType = getSelectedApplicationType();

    if (!selectedType) {
      const missing = ["Application Type"];
      if (missingFieldsList) {
        missingFieldsList.innerHTML = missing
          .map((m) => `<li style="font-weight:800;">${esc(m)}</li>`)
          .join("");
      }
      modal("#incompleteModal", "show");
      return false;
    }

    if (!state.subscriptionAccepted) {
      showSubscriptionAgreementForType(selectedType);
      return false;
    }

    if (!state.kasunduanAccepted) {
      showKasunduanAgreement();
      return false;
    }

    const { missingLabels, firstBadEl } = validateRequiredFields();

    if (missingLabels.length) {
      if (missingFieldsList) {
        missingFieldsList.innerHTML = missingLabels
          .map((m) => `<li style="font-weight:800;">${esc(m)}</li>`)
          .join("");
      }

      modal("#confirmSubmitModal", "hide");
      modal("#incompleteModal", "show");

      setTimeout(() => {
        if (firstBadEl) scrollToEl(firstBadEl);
        if (firstBadEl?.focus && firstBadEl.tagName !== "DIV") firstBadEl.focus();
      }, 350);

      return false;
    }

    renderSummary();
    modal("#confirmSubmitModal", "show");
    return false;
  });

  confirmSubmitBtn?.addEventListener("click", async (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (!state.subscriptionAccepted) {
      modal("#confirmSubmitModal", "hide");
      const selectedType = getSelectedApplicationType();
      if (selectedType) showSubscriptionAgreementForType(selectedType);
      return false;
    }

    if (!state.kasunduanAccepted) {
      modal("#confirmSubmitModal", "hide");
      showKasunduanAgreement();
      return false;
    }

    // FIX: Submit form data to PHP backend
    const fd = new FormData(form);

    // Append photo file (the input may not have a name attr)
    if (photoInput && photoInput.files && photoInput.files[0]) {
      fd.set("photo", photoInput.files[0]);
    }

    // Append signature file
    if (signatureInput && signatureInput.files && signatureInput.files[0]) {
      fd.set("signature_file", signatureInput.files[0]);
    }

    // Append agreement acceptance flags
    fd.set("subscription_agreement_accepted", state.subscriptionAccepted ? "1" : "0");
    fd.set("kasunduan_accepted", state.kasunduanAccepted ? "1" : "0");

    // Disable button to prevent double-submit
    if (confirmSubmitBtn) confirmSubmitBtn.disabled = true;

    try {
      const res = await fetch("api/membership_submit.php", {
        method: "POST",
        credentials: "include",
        body: fd
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.error || "Submission failed. Please try again.");
        if (confirmSubmitBtn) confirmSubmitBtn.disabled = false;
        return false;
      }
    } catch (err) {
      alert("Server error. Please check your connection and try again.");
      if (confirmSubmitBtn) confirmSubmitBtn.disabled = false;
      return false;
    }

    modal("#confirmSubmitModal", "hide");

    if (submitMsg) {
      submitMsg.style.display = "block";
      setTimeout(() => {
        submitMsg.style.display = "none";
      }, 1800);
    }

    setTimeout(() => {
      modal("#successSubmitModal", "show");
    }, 250);

    return false;
  });

  setSubscriptionAcceptedUI(false);
  setKasunduanAcceptedUI(false);
  updateLocks();
}