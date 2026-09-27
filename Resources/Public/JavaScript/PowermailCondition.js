class PowermailCondition {
  'use strict';

  /**
   * Form element (filled via constructor)
   */
  #form;

  /**
   * Is the form a morestep form?
   */
  #isMoreStepForm;

  /**
   * Selector for fields to be excluded from being sent to backend.
   * Might be useful for file upload fields.
   *
   * @type {string}
   */
  #excludedFieldsSelector = 'data-powermail-cond-excluded-fields';

  /**
   * The latest request to the condition endpoint; settles once its answer is applied or failed.
   */
  #pending = null;

  /**
   * Numbers the requests, so an answer that arrives after a newer request was sent is dropped.
   */
  #sequence = 0;

  /**
   * Set while a submission waits for the endpoint, so a second click does not send the form twice.
   */
  #submitting = false;

  /**
   * How long a submission waits for a pending condition request before it goes ahead anyway.
   */
  static PENDING_TIMEOUT = 10000;

  constructor(form) {
    this.#form = form;
    this.#form.powermailConditions = this;
    this.#isMoreStepForm = this.#form.classList.contains('powermail_morestep');
  }

  initialize = function () {
    const that = this;

    let formUid = this.#form.querySelector('input.powermail_form_uid').value;
    let formActionSelector = '#form-' + formUid + '-actions';

    if (document.querySelector(formActionSelector) === null) {
      // Loading conditions via AJAX
      that.#sendFormValuesToPowermailCond();
    } else {
      // Using prerendered conditions
      let actions = JSON.parse(document.querySelector(formActionSelector).textContent);
      that.#processActions(actions);
    }

    that.#fieldListener();
    that.#submitListener();
  }

  /**
   * Ask again with the values the form holds now, e.g. after the back/forward cache restored it.
   */
  refresh = function () {
    this.#submitting = false;
    this.#sendFormValuesToPowermailCond();
  }

  /**
   * Submits only once the conditions match what was entered last
   *
   * Technical background:
   * A visitor's last change (typing into a field, then clicking submit) sends the form to the
   * condition endpoint, and its answer decides which fields are hidden and disabled, and therefore
   * not sent. Submitting before that answer arrives sends the fields as they were before the
   * change. The endpoint can take a while - a condition may ask an external service - so waiting
   * a fixed 50ms was not enough.
   *
   * How it works:
   * 1. A submission another listener refused (e.g. client-side validation) stays refused
   * 2. The focused field is blurred, which fires "change" and asks the endpoint again
   * 3. The pending request is awaited, for at most PENDING_TIMEOUT
   * 4. Then the form is validated and submitted
   */
  #submitListener() {
    // don't setup race-condition listener for AJAX forms
    if (this.#form.getAttribute("data-powermail-ajax") === "true") {
      return;
    }

    this.#form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) {
        return;
      }
      event.preventDefault();
      if (this.#submitting) {
        return;
      }

      const activeElement = document.activeElement;
      if (activeElement && activeElement.tagName && this.#form.contains(activeElement)) {
        const tagName = activeElement.tagName.toLowerCase();
        if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
          activeElement.blur();
        }
      }

      this.#submitting = true;
      this.#afterPendingRequest().then(() => {
        if (this.#hasValidationErrors()) {
          this.#submitting = false;
          return;
        }

        this.#form.submit();
      });
    });
  }

  #afterPendingRequest() {
    const timeout = new Promise((resolve) => setTimeout(resolve, PowermailCondition.PENDING_TIMEOUT));
    return Promise.race([this.#pending || Promise.resolve(), timeout]);
  }

  /**
   * Check if the form has any validation errors, covering both native HTML5
   * validation and powermail's JavaScript validation.
   *
   * Note: reportValidity() alone is not sufficient because it always returns
   * true when the form has a "novalidate" attribute (i.e. when native
   * validation is disabled via TypoScript validation.native = 0).
   */
  #hasValidationErrors() {
    if (!this.#form.reportValidity()) {
      return true;
    }
    if (this.#form.classList.contains('powermail_form_error')) {
      return true;
    }
    return false;
  }

  #fieldListener() {
    const that = this;
    const fields = this.#getFieldsFromForm();
    fields.forEach((field) => {
      field.addEventListener('change', function(event) {
        that.#sendFormValuesToPowermailCond();
      })
    });
  }

  #sendFormValuesToPowermailCond () {
    const that = this;
    const dataToSend = this.#collectFormValues();

    if (this.#form.hasAttribute(this.#excludedFieldsSelector)) {
      // gather fields that should be excluded
      const excludedFields = this.#form.querySelectorAll(this.#form.getAttribute(this.#excludedFieldsSelector));
      excludedFields.forEach(e => {
        // and remove them from the payload being sent to the backend
        if (e.hasAttribute('name')) {
          dataToSend.delete(e.getAttribute('name'));
        }
      });
    }

    const sequence = ++this.#sequence;
    this.#pending = fetch(this.#getAjaxUri(), {body: dataToSend, method: 'post'})
      .then((resp) => resp.json())
      .then(function(data) {
        if (sequence !== that.#sequence) {
          // A newer request is on its way, and its answer describes the form as it is now.
          return;
        }
        if (data.loops > data.loopLimit) {
          console.log('Too much loops reached by parsing conditions and rules. Check for conflicting conditions.');
        } else {
          that.#processActions(data);
        }
      })
      .catch(function(error) {
        console.log(error);
      });
  };

  #processActions(data) {
    if (data.todo !== undefined) {
      for (let formUid in data.todo) {
        for (let pageUid in data.todo[formUid]) {

          // do actions with whole pages
          if (data.todo[formUid][pageUid]['#action'] === 'hide') {
            if (this.#isMoreStepForm) {
              PowermailCondition.hideElement(this.#getMoreStepToggleByUid(pageUid));
            }

            this.#hidePage(this.#getFieldsetByUid(pageUid));
          }
          if (data.todo[formUid][pageUid]['#action'] === 'un_hide') {
            if (this.#isMoreStepForm) {
              PowermailCondition.showElement(this.#getMoreStepToggleByUid(pageUid));
            }
            this.#showPage(this.#getFieldsetByUid(pageUid));
          }

          // do actions with single fields
          for (var fieldMarker in data.todo[formUid][pageUid]) {
            if (data.todo[formUid][pageUid][fieldMarker]['#action'] === 'hide') {
              this.#hideField(fieldMarker);
            }
            if (data.todo[formUid][pageUid][fieldMarker]['#action'] === 'un_hide') {
              this.#showField(fieldMarker);
            }
          }
        }
      }
    }
    let fieldsets = this.#form.querySelectorAll('.powermail_fieldset');
    fieldsets.forEach(function(fieldset) {
      if (window.getComputedStyle(fieldset).visibility === 'hidden') {
        // Making initially invisible fieldset visible
        fieldset.style.visibility = 'visible';
        fieldset.style.opacity = 1;
      }
    });

    // Tell the page the conditions are applied, with the whole response. Other extensions can
    // read what the endpoint added to it without a second request or wrapping fetch().
    this.#form.dispatchEvent(new CustomEvent('powermailcond:processed', {bubbles: true, detail: data}));
  };

  /**
   * Every value, including those of fields a condition disabled: the endpoint needs them to decide.
   * The fields are disabled again straight away - left enabled while the request runs, they would
   * be sent by a submission in the meantime although they are hidden.
   */
  #collectFormValues() {
    const disabledFields = this.#form.querySelectorAll('[disabled="disabled"]');
    disabledFields.forEach((field) => field.removeAttribute('disabled'));
    const dataToSend = new FormData(this.#form);
    disabledFields.forEach((field) => field.setAttribute('disabled', 'disabled'));
    return dataToSend;
  };

  #getFieldsFromForm() {
    return this.#form.querySelectorAll(
      'input:not([data-powermail-validation="disabled"]):not([type="hidden"]):not([type="submit"])'
      + ', textarea:not([data-powermail-validation="disabled"])'
      + ', select:not([data-powermail-validation="disabled"])'
    );
  };

  #getAjaxUri() {
    const container = document.querySelector('[data-condition-uri]');
    if (container === null) {
      console.log('Tag with data-condition-uri not found. Maybe TypoScript was not included.');
    }
    return container.getAttribute('data-condition-uri');
  };

  #showField(fieldMarker) {
    let wrappingContainer = this.#getWrappingContainerByMarkerName(fieldMarker);
    if (wrappingContainer !== null) {
      PowermailCondition.showElement(wrappingContainer);
      wrappingContainer.querySelectorAll('[type="submit"]').forEach((button) => button.removeAttribute('disabled'));
    }
    let field = this.#getFieldByMarker(fieldMarker);
    if (field !== null) {
      field.removeAttribute('disabled');
      this.#rerequireField(field);
    }
  };

  #hideField(fieldMarker) {
    let wrappingContainer = this.#getWrappingContainerByMarkerName(fieldMarker);
    if (wrappingContainer !== null) {
      PowermailCondition.hideElement(wrappingContainer);
      // A submit button has no field name to be found by, and a hidden one still sends the form
      // when Enter is pressed in a field (implicit submission) - unless it is disabled.
      wrappingContainer.querySelectorAll('[type="submit"]').forEach((button) => button.setAttribute('disabled', 'disabled'));
    }
    let field = this.#getFieldByMarker(fieldMarker);
    if (field !== null) {
      field.setAttribute('disabled', 'disabled');
      this.#derequireField(field);
    }
  };

  #showPage(page) {
    page.classList.remove('powermail-cond-hidden');
    if (!this.#isMoreStepForm) {
      PowermailCondition.showElement(page);
    }
  };

  #hidePage(page) {
    if (this.#isMoreStepForm) {
      page.classList.add('powermail-cond-hidden');
    } else {
      PowermailCondition.hideElement(page);
    }
  };

  #derequireField(field) {
    if (field.hasAttribute('required') || field.hasAttribute('data-powermail-required')) {
      field.removeAttribute('required');
      field.removeAttribute('data-powermail-required');
      field.setAttribute('data-powermailcond-required', 'required');
    }
  };

  #rerequireField(field) {
    if (field.getAttribute('data-powermailcond-required') === 'required') {
      if (this.#isHtml5ValidationActivated() || this.#isPowermailValidationActivated()) {
        field.setAttribute('required', 'required')
      }
    }
    field.removeAttribute('data-powermailcond-required');
  };

  #isPowermailValidationActivated() {
    return this.#form.getAttribute('data-powermail-validate') === 'data-powermail-validate';
  };

  #isHtml5ValidationActivated() {
    return this.#form.getAttribute('data-validate') === 'html5';
  };

  #getWrappingContainerByMarkerName(fieldMarker) {
    let wrappingContainer = this.#getFieldwrappingContainerByMarker(fieldMarker);
    if (wrappingContainer !== null) {
      return wrappingContainer;
    }

    let field = this.#getFieldByMarker(fieldMarker);
    if (field !== null) {
      let wrappingContainer = field.closest('.powermail_fieldwrap');
      if (wrappingContainer !== null) {
        return wrappingContainer;
      }
    }

    console.log('Error: Could not find field by fieldMarker "' + fieldMarker + '"');
    return null;
  };

  #getFieldByMarker(fieldMarker) {
    let fieldName = 'tx_powermail_pi1[field][' + fieldMarker + ']';
    return this.#form.querySelector('[name="' + fieldName +  '"]:not([type="hidden"])') ||
      this.#form.querySelector('[name="' + fieldName +  '[]"]');
  };

  #getFieldsetByUid(pageUid) {
    return this.#form.querySelector('.powermail_fieldset_' + pageUid);
  };

  #getMoreStepToggleByUid(pageUid) {
    return this.#form.querySelector(`.btn[data-powermail-fieldset="${pageUid}"]`)
  }

  #getFieldwrappingContainerByMarker(fieldMarker) {
    return this.#form.querySelector('.powermail_fieldwrap_' + fieldMarker);
  };

  static hideElement(element) {
    if (element !== null) {
      element.classList.add('powermail-cond-hidden');
      element.style.display = 'none';
    }
  }

  static showElement(element) {
    if (element !== null) {
      element.classList.remove('powermail-cond-hidden');
      element.style.display = '';
    }
  }
}

// We use "pageshow" instead of ready/DOMContentLoaded because this event
// specifically handles the backward/forward-navigation cache (bfcache)
// of browsers, so when someone returns to a already filled out form,
// the values get checked properly instead of sendFormValuesToPowermailCond
// receiving a practically empty initial form state.
window.addEventListener('pageshow', () => {
  const forms = document.querySelectorAll('.powermail_form');
  forms.forEach(function(form) {
    // Restored from the back/forward cache, the form still has its listeners: a second set would
    // send every change twice and submit twice. Ask again with the restored values instead.
    if (form.powermailConditions instanceof PowermailCondition) {
      form.powermailConditions.refresh();
      return;
    }
    let powermailConditions = new PowermailCondition(form);
    powermailConditions.initialize();
  });
});
