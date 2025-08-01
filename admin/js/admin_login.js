document.getElementById("adminLoginForm").addEventListener("submit", async function (e) {
  e.preventDefault();
  const password = document.getElementById("adminPassword").value;
  const errorMsg = document.getElementById("adminErrorMsg");

  try {
    // APIパス修正: /portfolio/api/auth_admin.php
    const response = await fetch("/portfolio/api/auth_admin.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `password=${encodeURIComponent(password)}`
    });

    const result = await response.text();
    if (result === "OK") {
      // ログイン成功時。PHP側でHttpOnly/SecureなCookieがセットされることを想定。
      // JavaScript側でも補助的にCookieをセット（HttpOnlyではないため、セキュリティ面で補助的な役割）
      // Cookieのpathも/portfolio/admin/に設定
      document.cookie = "admin_auth_check=1; max-age=86400; path=/portfolio/admin/"; // 24時間
      // リダイレクト先パス修正: /portfolio/admin/index.html
      window.location.href = "/portfolio/admin/index.html";
    } else {
      errorMsg.style.display = "block";
    }
  } catch (err) {
    console.error("管理画面ログインエラー:", err);
    errorMsg.textContent = "通信エラーが発生しました。時間を置いて再度お試しください。";
    errorMsg.style.display = "block";
  }
});