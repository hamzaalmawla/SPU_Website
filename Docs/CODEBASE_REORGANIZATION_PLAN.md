# Codebase Reorganization Plan

## Objective
Reorganize the `app/` directory structure to group related files by domain/feature, improving maintainability, discoverability, and scalability following Laravel and enterprise PHP best practices.

## Principles
1. **Domain-Driven Organization** - Group by business domain/feature
2. **Bounded Contexts** - Clear separation of concerns
3. **Scalability** - Structure supports future growth
4. **Consistency** - Same organizational pattern across all layers
5. **Laravel Conventions** - Respect framework standards
6. **PSR-4 Compliance** - Proper namespacing and autoloading

---

## Directory Structure

### 1. DTOs (`app/DTOs/`)

**Domain Structure:**
```
DTOs/
├── Homepage/
│   ├── HomepageDTO.php
│   ├── HomepageDraftDTO.php
│   ├── HomepageDraftDataDTO.php
│   ├── HomepageSectionDTO.php
│   ├── HomepageSectionDataDTO.php
│   ├── HomepageSectionTranslationDTO.php
│   ├── HomepageFeatureItemDTO.php
│   └── HomepageStatItemDTO.php
├── Page/
│   ├── PageDTO.php
│   ├── PageTranslationDTO.php
│   ├── PageMetadataDTO.php
│   ├── PageShellDataDTO.php
│   ├── PageDraftDTO.php
│   ├── PageDraftDataDTO.php
│   └── DraftPayloadDTO.php
├── Navigation/
│   ├── MenuItemDTO.php
│   ├── MenuItemDataDTO.php
│   ├── MenuTreeNodeDTO.php
│   ├── NavigationPayloadDTO.php
│   ├── NavigationTreeDTO.php
│   ├── NavigationActionDTO.php
│   └── LanguageSwitchLinkDTO.php
├── Media/
│   ├── MediaUploadResultDTO.php
│   └── FileInventoryItemDTO.php
├── Settings/
│   ├── SettingsDTO.php
│   ├── PublicSettingsDTO.php
│   ├── SettingValueDTO.php
│   ├── FooterSettingsDTO.php
│   ├── FooterColumnDTO.php
│   ├── SocialContactSettingsDTO.php
│   ├── SocialLinkDTO.php
│   ├── ApplyCtaSettingsDTO.php
│   └── EmergencyNoticeDTO.php
├── Seo/
│   ├── PageSeoDTO.php
│   ├── PageSeoInputDTO.php
│   └── SitemapEntryDTO.php
├── Contact/
│   ├── ContactPageDTO.php
│   ├── ContactPageContentDTO.php
│   ├── ContactLinkDTO.php
│   └── ContactSubmissionDataDTO.php
├── About/
│   ├── AboutLandingDTO.php
│   └── AboutContentPageDTO.php
├── EServices/
│   ├── EServicesPageDTO.php
│   └── EServicesPageContentDTO.php
├── Auth/
│   ├── LoginCredentialsDTO.php
│   ├── TotpEnrollmentDTO.php
│   └── AuditLogDTO.php
├── Content/
│   ├── ArticleCardDTO.php
│   ├── EventCardDTO.php
│   ├── ResearchCardDTO.php
│   ├── PersonDTO.php
│   ├── DirectorateDTO.php
│   └── PartnershipDTO.php
├── Preview/
│   ├── PreviewDTO.php
│   └── PreviewPayloadDTO.php
├── Legacy/
│   ├── RedirectRuleDTO.php
│   ├── RedirectResultDTO.php
│   ├── PatternRuleDTO.php
│   └── UnresolvedRequestDTO.php
├── Shared/
│   ├── BreadcrumbTrailDTO.php
│   ├── BreadcrumbItemDTO.php
│   ├── PaginatedResultDTO.php
│   ├── ValidationResultDTO.php
│   └── ValidationMessageDTO.php
└── Documentation/
    └── (existing docs)
```

---

### 2. Contracts (`app/Contracts/`)

**Domain Structure:**
```
Contracts/
├── Homepage/
│   ├── HomepageSectionServiceInterface.php
│   ├── HomepagePublishingServiceInterface.php
│   └── HomepagePreviewAssemblerInterface.php
├── Page/
│   ├── PageServiceInterface.php
│   ├── AboutPageServiceInterface.php
│   ├── ContactPageServiceInterface.php
│   └── EServicesPageServiceInterface.php
├── Navigation/
│   ├── MenuServiceInterface.php
│   └── NavigationServiceInterface.php
├── Media/
│   └── MediaServiceInterface.php
├── Settings/
│   └── SettingsServiceInterface.php
├── Seo/
│   ├── SeoMetadataServiceInterface.php
│   └── SitemapServiceInterface.php
├── Auth/
│   ├── AuthServiceInterface.php
│   └── TotpAuthenticatorInterface.php
├── Content/
│   └── PersonServiceInterface.php
├── Shared/
│   ├── CacheServiceInterface.php
│   ├── PreviewServiceInterface.php
│   ├── SlugServiceInterface.php
│   ├── AuditServiceInterface.php
│   └── ContinuityServiceInterface.php
```

---

### 3. Services (`app/Services/`)

**Domain Structure:**
```
Services/
├── Homepage/
│   ├── HomepageSectionService.php
│   ├── HomepagePublishingService.php
│   ├── HomepagePreviewAssembler.php
│   ├── HomepageDraftReader.php
│   └── HomepageSectionValidator.php
├── Page/
│   ├── PageService.php
│   ├── AboutPageService.php
│   ├── ContactPageService.php
│   ├── EServicesPageService.php
│   ├── PageDraftService.php
│   ├── PagePublicReadService.php
│   ├── PagePublishabilityValidator.php
│   └── PageUrlResolver.php
├── Navigation/
│   ├── MenuService.php
│   └── NavigationService.php
├── Media/
│   ├── MediaService.php
│   └── MediaFileValidator.php
├── Settings/
│   └── SettingsService.php
├── Seo/
│   ├── SeoMetadataService.php
│   └── SitemapService.php
├── Auth/
│   ├── AuthService.php
│   └── TotpAuthenticator.php
├── Content/
│   └── PersonService.php
├── Preview/
│   ├── PreviewService.php
│   └── PreviewTokenStore.php
├── Shared/
│   ├── CacheService.php
│   ├── SlugService.php
│   ├── AuditService.php
│   └── ContinuityService.php
└── Placeholders/
    └── (existing placeholders)
```

---

### 4. Models (`app/Models/`)

**Domain Structure:**
```
Models/
├── Homepage/
│   ├── HomepageSection.php
│   ├── HomepageSectionTranslation.php
│   └── HomepageDraft.php
├── Page/
│   ├── Page.php
│   ├── PageTranslation.php
│   ├── PageDraft.php
│   ├── PageSeoMeta.php
│   ├── AboutPage.php
│   ├── AboutPageTranslation.php
│   └── UnresolvedLegacyRequest.php
├── Navigation/
│   └── MenuItem.php
├── Media/
│   └── MediaAsset.php
├── Settings/
│   └── Setting.php
├── User/
│   ├── User.php
│   └── Role.php
├── Person/
│   ├── Person.php
│   ├── PersonTranslation.php
│   ├── FacultyMember.php
│   ├── FacultyMemberTranslation.php
│   ├── CouncilMember.php
│   └── CouncilMemberTranslation.php
├── Faculty/
│   ├── Faculty.php
│   ├── FacultyTranslation.php
│   ├── Department.php
│   ├── DepartmentTranslation.php
│   ├── Council.php
│   └── CouncilTranslation.php
├── Research/
│   ├── ResearchPublication.php
│   ├── ResearchPublicationTranslation.php
│   └── ResearchFile.php
├── Contact/
│   ├── ContactMessage.php
│   ├── Complaint.php
│   ├── ComplaintCategory.php
│   └── ComplaintCategoryTranslation.php
├── Location/
│   ├── Country.php
│   ├── CountryTranslation.php
│   ├── City.php
│   └── CityTranslation.php
├── Career/
│   ├── Alumni.php
│   ├── AlumniTranslation.php
│   ├── HonorStudent.php
│   ├── HonorStudentTranslation.php
│   ├── CareerLink.php
│   └── CareerLinkTranslation.php
├── Content/
│   ├── Directorate.php
│   ├── DirectorateTranslation.php
│   ├── Partnership.php
│   ├── PartnershipTranslation.php
│   ├── Faq.php
│   ├── FaqTranslation.php
│   ├── FaqCategory.php
│   └── FaqCategoryTranslation.php
├── Legacy/
│   ├── LegacyExactRedirect.php
│   ├── LegacyPatternRule.php
│   ├── LegacyFileInventory.php
│   └── LegacyRecordSnapshot.php
└── Shared/
    ├── AuditLog.php
    ├── PreviewToken.php
    ├── MigrationLog.php
    └── MigrationRejection.php
```

---

### 5. Http/Controllers (`app/Http/Controllers/`)

**Structure:**
```
Controllers/
├── Controller.php (base)
├── Public/
│   ├── HomeController.php
│   ├── PageController.php
│   ├── AboutController.php
│   ├── EServicesController.php
│   ├── PublicContactController.php
│   ├── PreviewController.php
│   └── SitemapController.php
└── Admin/
    └── (Filament handles admin controllers)
```

---

## Migration Strategy

### Phase 1: DTOs (Highest Impact, Lowest Risk)
1. Create subdirectories
2. Move files using `smart_relocate` (auto-updates imports)
3. Verify autoloading
4. Run tests

### Phase 2: Contracts (Medium Impact)
1. Create subdirectories
2. Move interface files
3. Update service provider bindings
4. Verify dependency injection

### Phase 3: Services (High Impact)
1. Create subdirectories
2. Move service files
3. Update service provider registrations
4. Verify all interface implementations

### Phase 4: Models (Critical)
1. Create subdirectories
2. Move model files carefully
3. Update factory namespaces
4. Update seeders and migrations
5. Clear and rebuild caches
6. Extensive testing

### Phase 5: Controllers (Lower Priority)
1. Create Public/ subdirectory
2. Move public controllers
3. Update route files

---

## Post-Migration Checklist

- [ ] Run `composer dump-autoload`
- [ ] Clear all caches: `php artisan optimize:clear`
- [ ] Verify all service bindings resolve
- [ ] Run test suite (if available)
- [ ] Check Filament admin panel loads
- [ ] Verify public routes work
- [ ] Check all imports are correct
- [ ] Update any documentation references
- [ ] Git commit with detailed message

---

## Benefits

1. **Improved Discoverability** - Related files grouped together
2. **Better IDE Navigation** - Easier to find specific domain code
3. **Reduced Cognitive Load** - Clear domain boundaries
4. **Scalability** - Easy to add new features within domains
5. **Team Collaboration** - Clearer ownership and responsibilities
6. **Easier Testing** - Domain isolation simplifies unit testing
7. **Reduced Merge Conflicts** - Changes localized to specific domains

---

## Risks & Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking imports | Use `smart_relocate` tool (auto-updates) |
| Service binding issues | Verify container resolution after each phase |
| Model relationships break | Test thoroughly, use qualified class names |
| Third-party packages | Check vendor packages don't hardcode paths |
| Cache issues | Clear all caches between phases |

---

## Timeline Estimate

- Phase 1 (DTOs): ~10-15 minutes
- Phase 2 (Contracts): ~5-10 minutes
- Phase 3 (Services): ~10-15 minutes
- Phase 4 (Models): ~15-20 minutes
- Phase 5 (Controllers): ~5 minutes
- Testing & Verification: ~10 minutes

**Total: ~55-75 minutes**

---

*Document created: 2026-06-16*
*Status: Ready for execution*
