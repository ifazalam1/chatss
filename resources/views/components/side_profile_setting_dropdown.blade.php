 <div class="pt-2 border-t border-gray-700 relative">
                    <!-- Profile / Settings Dropdown Trigger -->
                    <div id="profileDropdownTrigger" class="p-3 rounded-md chat-hover cursor-pointer flex items-center justify-between">
                        <div class="flex items-center">
                            <img class="rounded-full h-8 w-8 mr-2" src="{{ Auth::user()->photo ? asset('backend/uploads/user/' . Auth::user()->photo) : asset('build/images/users/avatar-1.jpg') }}" alt="Avatar">
                            <span class="text-gray-200">{{ Auth::user()->name }}</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <!-- Profile / Settings Dropdown Menu -->
                    <div id="profileDropdownMenu" class="absolute bottom-full mb-2 left-0 w-full z-20 hidden">
                        <ul class="bg-gray-800 text-white rounded-md border border-gray-700 shadow-lg overflow-hidden">
                            <li>
                                <a href="{{ route('admin.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-account-circle me-1"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('home') }}" target="_blank" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-view-dashboard-variant me-1"></i> Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('home') }}" target="_blank" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-home me-1"></i> Home Page
                                </a>
                            </li>
                            {{-- <li>
                                <a href="{{ route('ai.image.gallery') }}" target="_blank" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-folder-image me-1"></i> Image Gallery
                                </a>
                            </li> --}}
                            <li class="border-t border-gray-700"></li>
                            {{-- <li class="flex justify-between items-center px-4 py-2">
                                <div>
                                    <i class="mdi mdi-wallet me-1"></i> Plan: <b>{{ Auth::user()->plan->name ?? 'Free' }}</b>
                                </div>
                                <a href="{{ route('pricing') }}" class="btn btn-sm gradient-btn-others">Upgrade</a>
                            </li> --}}
                            <li class="px-4 py-2">
                                <i class="mdi mdi-counter me-1"></i>
                                <b>Balance:</b> Tokens: <b>{{ Auth::user()->tokens_left }}</b>, Credits: <b>{{ Auth::user()->credits_left }}</b>
                            </li>
                            {{-- <li>
                                <a href="{{ url('/billing/portal') }}" target="_blank" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-account-cash me-1"></i> Billing
                                </a>
                            </li> --}}
                            <li>
                                <a data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" aria-controls="theme-settings-offcanvas" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="mdi mdi-spin mdi-cog-outline me-1"></i> Theme Customizer
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="bx bx-power-off me-1"></i> Logout
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Toggle Dropdown Script -->
                <script>
                    const profileTrigger = document.getElementById('profileDropdownTrigger');
                    const profileMenu = document.getElementById('profileDropdownMenu');

                    profileTrigger.addEventListener('click', () => {
                        profileMenu.classList.toggle('hidden');
                    });

                    // Optional: Close if click outside
                    document.addEventListener('click', (e) => {
                        if (!profileTrigger.contains(e.target) && !profileMenu.contains(e.target)) {
                            profileMenu.classList.add('hidden');
                        }
                    });
                </script>