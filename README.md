# Form Field Naming Guide

This guide explains how to correctly name your form input fields so that our PHP script can detect and process them automatically.

---

## 🔹 Naming Rules

All input fields must follow this pattern:

```
[a-z]+-(email|key)
```

* **Prefix**: any lowercase letters (`a`–`z`)
* **Suffix**: either `-email` or `-key`

> Example valid names: `y-email`, `user-email`, `a-key`, `login-key`
> Example invalid names: `Email`, `useremail`, `123-email`, `email-y`

---

## 🔹 Requirements

1. **Email field is required**

    * At least one field ending with `-email` or `-key` must exist in your form.
    * Mark it as `required` in HTML to prevent empty submissions.

2. **First match rule**

    * The script only uses the **first matching field** it finds in the form submission.
    * Avoid creating multiple `-email` fields unless you intend to use only the first one.

3. **Client-side validation only**

    * There is **no server-side validation**.
    * Ensure you use `type="email"` and `required` attributes or custom JavaScript validation.

---

## 🔹 Example HTML Form

```html
<form method="post">
  <!-- Required email field -->
  <input type="email" name="i-email" required placeholder="Enter your email" />
  <button type="submit">Submit</button>
</form>
```

---

### ⚠️ Notes

* Use **lowercase letters** only in field names.
* Only `-email` or `-key` suffixes are recognized.
* Ensure the **first match** is the one you want captured by the PHP script.
* All other fields not matching the pattern will be ignored.

---
