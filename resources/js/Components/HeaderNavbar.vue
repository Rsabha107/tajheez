<script setup>
import { Link } from "@inertiajs/vue3";
// import { onMounted, onUnmounted } from "vue";

// onMounted(() => {
//   console.log("Header Navbar Mounted");
//   document.body.classList.add("none-login-bg");
// });

// onUnmounted(() => {
//   document.body.classList.remove("none-login-bg");
// });

</script>
<template>
  <div class="navbar-header">
    <div class="d-flex">
      <!-- LOGO -->
      <div class="navbar-brand-box">
        <Link class="logo logo-dark" :href="route('home')">
          <span class="logo-sm">
            <img alt="" height="22" src="/assets/images/logo.svg" />
          </span>
          <span class="logo-lg">
            <img alt="" height="17" src="/assets/images/logo-dark.png" />
          </span>
        </Link>
        <Link class="logo logo-light" :href="route('home')">
          <span class="logo-sm">
            <img alt="" height="22" src="/assets/images/logo-light.svg" />
          </span>
          <span class="logo-lg">
            <img alt="" height="19" src="/assets/images/users/sparkle_hrms.jpg" />
          </span>
        </Link>
      </div>
      <button
        class="btn btn-sm px-3 font-size-16 header-item waves-effect"
        id="vertical-menu-btn"
        type="button"
      >
        <i class="fa fa-fw fa-bars"> </i>
      </button>
      <!-- App Search-->
      <!-- <form class="app-search d-none d-lg-block">
        <div class="position-relative">
          <input class="form-control" placeholder="Search..." type="text" />
          <span class="bx bx-search-alt"> </span>
        </div>
      </form>
      <div class="dropdown dropdown-mega d-none d-lg-block ms-2">
        <button
          aria-expanded="false"
          aria-haspopup="false"
          class="btn header-item waves-effect"
          data-bs-toggle="dropdown"
          type="button"
        >
          <span key="t-megamenu"> Mega Menu </span>
          <i class="mdi mdi-chevron-down"> </i>
        </button>
        <div class="dropdown-menu dropdown-megamenu">
          <div class="row">
            <div class="col-sm-8">
              <div class="row">
                <div class="col-md-4">
                  <h5 class="font-size-14" key="t-ui-components">
                    UI Components
                  </h5>
                  <ul class="list-unstyled megamenu-list">
                    <li>
                      <a href="javascript:void(0);" key="t-lightbox">
                        Lightbox
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-range-slider">
                        Range Slider
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-sweet-alert">
                        Sweet Alert
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-rating"> Rating </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-forms"> Forms </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-tables"> Tables </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-charts"> Charts </a>
                    </li>
                  </ul>
                </div>
                <div class="col-md-4">
                  <h5 class="font-size-14" key="t-applications">
                    Applications
                  </h5>
                  <ul class="list-unstyled megamenu-list">
                    <li>
                      <a href="javascript:void(0);" key="t-ecommerce">
                        Ecommerce
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-calendar">
                        Calendar
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-email"> Email </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-projects">
                        Projects
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-tasks"> Tasks </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-contacts">
                        Contacts
                      </a>
                    </li>
                  </ul>
                </div>
                <div class="col-md-4">
                  <h5 class="font-size-14" key="t-extra-pages">Extra Pages</h5>
                  <ul class="list-unstyled megamenu-list">
                    <li>
                      <a href="javascript:void(0);" key="t-light-sidebar">
                        Light Sidebar
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-compact-sidebar">
                        Compact Sidebar
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-horizontal">
                        Horizontal layout
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-maintenance">
                        Maintenance
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-coming-soon">
                        Coming Soon
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-timeline">
                        Timeline
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-faqs"> FAQs </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="row">
                <div class="col-sm-6">
                  <h5 class="font-size-14" key="t-ui-components">
                    UI Components
                  </h5>
                  <ul class="list-unstyled megamenu-list">
                    <li>
                      <a href="javascript:void(0);" key="t-lightbox">
                        Lightbox
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-range-slider">
                        Range Slider
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-sweet-alert">
                        Sweet Alert
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-rating"> Rating </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-forms"> Forms </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-tables"> Tables </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" key="t-charts"> Charts </a>
                    </li>
                  </ul>
                </div>
                <div class="col-sm-5">
                  <div>
                    <img
                      alt=""
                      class="img-fluid mx-auto d-block"
                      src="assets/images/megamenu-img.png"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div> -->
    </div>
    <div class="d-flex">
      <div class="dropdown d-inline-block d-lg-none ms-2">
        <button
          aria-expanded="false"
          aria-haspopup="true"
          class="btn header-item noti-icon waves-effect"
          data-bs-toggle="dropdown"
          id="page-header-search-dropdown"
          type="button"
        >
          <i class="mdi mdi-magnify"> </i>
        </button>
        <div
          aria-labelledby="page-header-search-dropdown"
          class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
        >
          <form class="p-3">
            <div class="form-group m-0">
              <div class="input-group">
                <input
                  aria-label="Recipient's username"
                  class="form-control"
                  placeholder="Search ..."
                  type="text"
                />
                <div class="input-group-append">
                  <button class="btn btn-primary" type="submit">
                    <i class="mdi mdi-magnify"> </i>
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="dropdown d-inline-block">
        <button
          aria-expanded="false"
          aria-haspopup="true"
          class="btn header-item waves-effect"
          data-bs-toggle="dropdown"
          type="button"
        >
          <img
            alt="Header Language"
            height="16"
            class="img-flag"
            id="header-lang-img"
            src="assets/images/flags/us.jpg"
          />
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <!-- item-->
          <a
            class="dropdown-item notify-item language"
            data-lang="en"
            href="javascript:void(0);"
          >
            <img
              alt="user-image"
              class="me-1 img-flag"
              height="12"
              src="assets/images/flags/us.jpg"
            />
            <span class="align-middle text-black"> English </span>
          </a>
          <!-- item-->
          <a
            class="dropdown-item notify-item language"
            data-lang="sp"
            href="javascript:void(0);"
          >
            <img
              alt="user-image "
              class="me-1 img-flag"
              height="12"
              src="assets/images/flags/spain.jpg"
            />
            <span class="align-middle"> Spanish </span>
          </a>
          <!-- item-->
          <a
            class="dropdown-item notify-item language"
            data-lang="gr"
            href="javascript:void(0);"
          >
            <img
              alt="user-image"
              class="me-1 img-flag"
              height="12"
              src="assets/images/flags/germany.jpg"
            />
            <span class="align-middle"> German </span>
          </a>
          <!-- item-->
          <a
            class="dropdown-item notify-item language"
            data-lang="it"
            href="javascript:void(0);"
          >
            <img
              alt="user-image"
              class="me-1 img-flag"
              height="12"
              src="assets/images/flags/italy.jpg"
            />
            <span class="align-middle"> Italian </span>
          </a>
          <!-- item-->
          <a
            class="dropdown-item notify-item language"
            data-lang="ru"
            href="javascript:void(0);"
          >
            <img
              alt="user-image"
              class="me-1 img-flag"
              height="12"
              src="assets/images/flags/russia.jpg"
            />
            <span class="align-middle"> Russian </span>
          </a>
        </div>
      </div>
      <div class="dropdown d-none d-lg-inline-block ms-1">
        <button
          aria-expanded="false"
          aria-haspopup="true"
          class="btn header-item noti-icon waves-effect"
          data-bs-toggle="dropdown"
          type="button"
        >
          <i class="bx bx-customize"> </i>
        </button>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <div class="px-lg-2">
            <div class="row g-0">
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img alt="Github" src="assets/images/brands/github.png" />
                  <span> GitHub </span>
                </a>
              </div>
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img
                    alt="bitbucket"
                    src="assets/images/brands/bitbucket.png"
                  />
                  <span> Bitbucket </span>
                </a>
              </div>
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img alt="dribbble" src="assets/images/brands/dribbble.png" />
                  <span> Dribbble </span>
                </a>
              </div>
            </div>
            <div class="row g-0">
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img alt="dropbox" src="assets/images/brands/dropbox.png" />
                  <span> Dropbox </span>
                </a>
              </div>
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img
                    alt="mail_chimp"
                    src="assets/images/brands/mail_chimp.png"
                  />
                  <span> Mail Chimp </span>
                </a>
              </div>
              <div class="col">
                <a class="dropdown-icon-item" href="#">
                  <img alt="slack" src="assets/images/brands/slack.png" />
                  <span> Slack </span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="dropdown d-none d-lg-inline-block ms-1">
        <button
          class="btn header-item noti-icon waves-effect"
          data-bs-toggle="fullscreen"
          type="button"
        >
          <i class="bx bx-fullscreen"> </i>
        </button>
      </div>
      <div class="dropdown d-inline-block">
        <button
          aria-expanded="false"
          aria-haspopup="true"
          class="btn header-item noti-icon waves-effect"
          data-bs-toggle="dropdown"
          id="page-header-notifications-dropdown"
          type="button"
        >
          <i class="bx bx-bell bx-tada"> </i>
          <span class="badge bg-danger rounded-pill"> 3 </span>
        </button>
        <div
          aria-labelledby="page-header-notifications-dropdown"
          class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
        >
          <div class="p-3">
            <div class="row align-items-center">
              <div class="col">
                <h6 class="m-0" key="t-notifications">Notifications</h6>
              </div>
              <div class="col-auto">
                <a class="small" href="#!" key="t-view-all"> View All </a>
              </div>
            </div>
          </div>
          <div data-simplebar="" style="max-height: 230px">
            <a class="text-reset notification-item" href="javascript: void(0);">
              <div class="d-flex">
                <div class="avatar-xs me-3">
                  <span
                    class="avatar-title bg-primary rounded-circle font-size-16"
                  >
                    <i class="bx bx-cart"> </i>
                  </span>
                </div>
                <div class="flex-grow-1">
                  <h6 class="mb-1" key="t-your-order">Your order is placed</h6>
                  <div class="font-size-12 text-muted">
                    <p class="mb-1" key="t-grammer">
                      If several languages coalesce the grammar
                    </p>
                    <p class="mb-0">
                      <i class="mdi mdi-clock-outline"> </i>
                      <span key="t-min-ago"> 3 min ago </span>
                    </p>
                  </div>
                </div>
              </div>
            </a>
            <a class="text-reset notification-item" href="javascript: void(0);">
              <div class="d-flex">
                <img
                  alt="user-pic"
                  class="me-3 rounded-circle avatar-xs"
                  src="assets/images/users/avatar-3.jpg"
                />
                <div class="flex-grow-1">
                  <h6 class="mb-1">James Lemire</h6>
                  <div class="font-size-12 text-muted">
                    <p class="mb-1" key="t-simplified">
                      It will seem like simplified English.
                    </p>
                    <p class="mb-0">
                      <i class="mdi mdi-clock-outline"> </i>
                      <span key="t-hours-ago"> 1 hours ago </span>
                    </p>
                  </div>
                </div>
              </div>
            </a>
            <a class="text-reset notification-item" href="javascript: void(0);">
              <div class="d-flex">
                <div class="avatar-xs me-3">
                  <span
                    class="avatar-title bg-success rounded-circle font-size-16"
                  >
                    <i class="bx bx-badge-check"> </i>
                  </span>
                </div>
                <div class="flex-grow-1">
                  <h6 class="mb-1" key="t-shipped">Your item is shipped</h6>
                  <div class="font-size-12 text-muted">
                    <p class="mb-1" key="t-grammer">
                      If several languages coalesce the grammar
                    </p>
                    <p class="mb-0">
                      <i class="mdi mdi-clock-outline"> </i>
                      <span key="t-min-ago"> 3 min ago </span>
                    </p>
                  </div>
                </div>
              </div>
            </a>
            <a class="text-reset notification-item" href="javascript: void(0);">
              <div class="d-flex">
                <img
                  alt="user-pic"
                  class="me-3 rounded-circle avatar-xs"
                  src="assets/images/users/avatar-4.jpg"
                />
                <div class="flex-grow-1">
                  <h6 class="mb-1">Salena Layfield</h6>
                  <div class="font-size-12 text-muted">
                    <p class="mb-1" key="t-occidental">
                      As a skeptical Cambridge friend of mine occidental.
                    </p>
                    <p class="mb-0">
                      <i class="mdi mdi-clock-outline"> </i>
                      <span key="t-hours-ago"> 1 hours ago </span>
                    </p>
                  </div>
                </div>
              </div>
            </a>
          </div>
          <div class="p-2 border-top d-grid">
            <a
              class="btn btn-sm btn-link font-size-14 text-center"
              href="javascript:void(0)"
            >
              <i class="mdi mdi-arrow-right-circle me-1"> </i>
              <span key="t-view-more"> View More.. </span>
            </a>
          </div>
        </div>
      </div>
      <div class="dropdown d-inline-block">
        <button
          aria-expanded="false"
          aria-haspopup="true"
          class="btn header-item waves-effect d-flex align-items-center"
          data-bs-toggle="dropdown"
          id="page-header-user-dropdown"
          type="button"
        >
          <img
            alt="Header Avatar"
            class="rounded-circle header-profile-user"
            src="assets/images/users/sparkle_hrms.jpg"
          />
          <span class="d-none d-xl-inline-block ms-1" key="t-StarCode Kh">
            {{ $page.props.auth.user.name }}
          </span>
          <i class="mdi mdi-chevron-down d-none d-xl-inline-block"> </i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <!-- item-->
          <a class="dropdown-item" href="#">
            <i class="bx bx-user font-size-16 align-middle me-1"> </i>
            <span key="t-profile"> Profile </span>
          </a>
          <a class="dropdown-item" href="#">
            <i class="bx bx-wallet font-size-16 align-middle me-1"> </i>
            <span key="t-my-wallet"> My Wallet </span>
          </a>
          <a class="dropdown-item d-block" href="#">
            <span class="badge bg-success float-end"> 11 </span>
            <i class="bx bx-wrench font-size-16 align-middle me-1"> </i>
            <span key="t-settings"> Settings </span>
          </a>
          <a class="dropdown-item" href="#">
            <i class="bx bx-lock-open font-size-16 align-middle me-1"> </i>
            <span key="t-lock-screen"> Lock screen </span>
          </a>
          <div class="dropdown-divider"></div>
          <Link class="dropdown-item text-danger" :href="route('logout')" method="post">
            <i
              class="bx bx-power-off font-size-16 align-middle me-1 text-danger"
            >
            </i>
            <span key="t-logout"> Logout </span>
          </Link>
        </div>
      </div>
      <div class="dropdown d-inline-block">
        <button
          class="btn header-item noti-icon right-bar-toggle waves-effect"
          type="button"
        >
          <!-- <i class="bx bx-cog bx-spin"> </i> -->
        </button>
      </div>
    </div>
  </div>
</template>