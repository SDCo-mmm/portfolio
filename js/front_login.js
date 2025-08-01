document.getElementById("loginForm").addEventListener("submit", async function (e) {
  e.preventDefault();
  const password = document.getElementById("password").value;
  const errorMsg = document.getElementById("errorMsg");

  try {
    // APIのパス修正: /api/auth_front.php
    const response = await fetch("/portfolio/api/auth_front.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `password=${encodeURIComponent(password)}`
    });

    const result = await response.text();
    if (result === "OK") {
      // ログイン成功時。PHP側でHttpOnly/SecureなCookieがセットされることを想定。
      // JavaScript側でも補助的にCookieをセット（HttpOnlyではないため、セキュリティ面で補助的な役割）
      // Cookieのpathも/portfolio/に設定
      document.cookie = "front_auth_check=1; max-age=86400; path=/portfolio/"; // 24時間
      // リダイレクト先パス修正: /index.html
      window.location.href = "/portfolio/index.html";
    } else {
      errorMsg.style.display = "block";
    }
  } catch (err) {
    console.error("ログインエラー:", err);
    errorMsg.textContent = "通信エラーが発生しました。時間を置いて再度お試しください。";
    errorMsg.style.display = "block";
  }
});