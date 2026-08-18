<nav class="pf-mobile-nav" aria-label="เมนูหลักบนมือถือ">
    <a href="{{ route('user.dashboard') }}" class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
        <i class="fas fa-chart-line" aria-hidden="true"></i><span>ภาพรวม</span>
    </a>
    <a href="{{ route('user.dashboard.table') }}" class="{{ request()->routeIs('user.dashboard.table') ? 'active' : '' }}">
        <i class="fas fa-layer-group" aria-hidden="true"></i><span>โครงการ</span>
    </a>
    <a href="{{ route('user.sales.create') }}" class="{{ request()->routeIs('user.sales.create') ? 'active' : '' }}">
        <i class="fas fa-plus-circle" aria-hidden="true"></i><span>เพิ่มใหม่</span>
    </a>
    <a href="{{ route('user.profile') }}" class="{{ request()->routeIs('user.profile') ? 'active' : '' }}">
        <i class="fas fa-user-circle" aria-hidden="true"></i><span>โปรไฟล์</span>
    </a>
</nav>
