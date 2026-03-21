<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Sign In</title>
		<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
		<script src="https://cdn.tailwindcss.com"></script>
		<link rel="stylesheet" href="css/login.css">
	</head>
	<body class="bg-gray-100 font-poppins">
		<div class="min-h-screen flex items-center justify-center p-6">
			<div class="w-full max-w-6xl rounded-2xl overflow-hidden shadow-2xl grid grid-cols-1 md:grid-cols-2">
				<!-- Left panel -->
				<div class="relative hidden md:block">
					<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/left-panel.jpg.jpeg');"></div>
					<!-- subtle tint to harmonize with right panel -->
					<div class="absolute inset-0 left-tint pointer-events-none"></div>
					<div class="relative z-10 h-full p-8 flex flex-col justify-between text-white">
						<div class="flex items-start justify-between">
							<div class="text-2xl font-bold tracking-wide"></div>
							<a href="#" class="text-sm bg-white/10 px-3 py-1 rounded-full"></a>
						</div>
						<div class="mb-6">
							<h2 class="text-3xl md:text-4xl font-semibold"></h2>
						</div>
					</div>
				</div>

				<!-- Right panel (form) -->
				<div class="bg-[#20204D] p-8 md:p-12 flex items-center">
					<div class="w-full max-w-md mx-auto text-white">
						<h1 class="text-2xl font-bold mb-2">NTMH, ALS</h1>
						<p class="text-sm text-gray-300 mb-6">Welcome back — please enter your username and password.</p>

						<form action="login_process.php" method="post" class="space-y-4">
							<div>
								<label class="block text-sm text-gray-300 mb-2">Username</label>
								<input name="username" type="text" required class="w-full px-4 py-3 rounded bg-[#213683] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Enter your username">
							</div>
							<div>
								<label class="block text-sm text-gray-300 mb-2">Password</label>
								<div class="relative">
									<input id="password" name="password" type="password" required class="w-full px-4 py-3 rounded bg-[#213683] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Enter your password">
									<button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-400" aria-label="toggle password visibility">👁️</button>
								</div>
							</div>

							<div class="flex items-center justify-between text-sm text-gray-300">
								<label class="flex items-center gap-2"><input type="checkbox" class="form-checkbox h-4 w-4 text-purple-500"> Remember me</label>
								<a href="#" class="text-purple-300 hover:underline">Forgot password?</a>
							</div>

							<button type="submit" class="w-full py-3 rounded bg-gradient-to-r from-purple-600 to-indigo-600 font-semibold hover:opacity-95">Login</button>
						</form>

						<div class="mt-6 text-center text-gray-400">
							Quick Link
						</div>

						<div class="mt-4 grid grid-cols-2 gap-4">
							<button class="py-2 px-4 rounded border border-gray-600 bg-transparent text-white flex items-center justify-center gap-2"> 
								<img src="https://www.svgrepo.com/show/355037/google.svg" alt="Google" class="w-5 h-5">
								Google
							</button>
							<a href="admin_login.php" class="py-2 px-4 rounded border border-gray-600 bg-transparent text-white flex items-center justify-center gap-2 no-underline"> 
								<!-- simple admin/user icon -->
								<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 5v1h16v-1c0-2.33-2.67-5-8-5z"/></svg>
								Admin Login
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script>
function togglePassword() {
	var passwordField = document.getElementById("password");

	if (passwordField.type === "password") {
		passwordField.type = "text";
	} else {
		passwordField.type = "password";
	}
}
</script>
	</body>
</html>