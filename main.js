(function () {
  const $ = (id) => document.getElementById(id);

  const unitNameEl = $('unitName');
  const unitIdEl = $('unitId');
  const arrivalEl = $('arrival');
  const departureEl = $('departure');
  const occupantsEl = $('occupants');
  const agesEl = $('ages');
  const submitBtn = $('submitBtn');
  const outputEl = $('output');

  function setLoading(loading) {
    submitBtn.disabled = loading;
    submitBtn.textContent = loading ? 'Loading…' : 'Get Rates';
  }

  function parseAges(input) {
    if (!input.trim()) return [];
    return input
      .split(',')
      .map((s) => s.trim())
      .filter((s) => s.length)
      .map((s) => Number(s))
      .filter((n) => Number.isFinite(n) && n >= 0);
  }

  async function submit() {
    outputEl.textContent = '';

    const unitNameVal = unitNameEl.value;
    const customId = unitIdEl.value ? Number(unitIdEl.value) : null;

    let unitNameToSend = unitNameVal;
    if (!unitNameVal && customId !== null && Number.isFinite(customId)) {
      unitNameToSend = String(customId); // backend will accept numeric as string
    }

    const arrival = arrivalEl.value.trim();
    const departure = departureEl.value.trim();
    const occupants = Number(occupantsEl.value);
    const ages = parseAges(agesEl.value);

    // Basic validation
    if (!unitNameToSend) {
      outputEl.textContent = 'Please select a Unit Name or enter a Custom Unit Type ID.';
      return;
    }
    if (!arrival || !departure) {
      outputEl.textContent = 'Please enter Arrival and Departure dates in dd/mm/yyyy format.';
      return;
    }
    if (!Number.isFinite(occupants) || occupants < 1) {
      outputEl.textContent = 'Occupants must be a positive integer.';
      return;
    }
    if (ages.length !== occupants) {
      outputEl.textContent = 'Ages count must match Occupants.';
      return;
    }

    const payload = {
      'Unit Name': unitNameToSend,
      'Arrival': arrival,
      'Departure': departure,
      'Occupants': occupants,
      'Ages': ages,
    };

    setLoading(true);
    try {
      const res = await fetch('/api/rates.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch {
        json = { raw: text };
      }
      outputEl.textContent = JSON.stringify(json, null, 2);
    } catch (err) {
      outputEl.textContent = 'Network error: ' + (err && err.message ? err.message : String(err));
    } finally {
      setLoading(false);
    }
  }

  submitBtn.addEventListener('click', submit);
})();
