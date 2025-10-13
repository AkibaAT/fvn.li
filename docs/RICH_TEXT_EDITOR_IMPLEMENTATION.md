# Rich Text Editor Implementation Specification

## Overview

This document outlines the implementation of a comprehensive rich text editing system for game detail pages, featuring
inline editing capabilities, media embeds, advanced image management, and real-time collaborative editing.

## Technology Stack

### Frontend

- **Tiptap** - Modern rich text editor framework built on ProseMirror
- **React/TypeScript** - UI components and type safety
- **Inertia.js** - Existing SPA framework integration
- **Tailwind CSS** - Styling and responsive design

### Backend

- **Laravel 12** - API endpoints and business logic
- **PostgreSQL** - Data persistence
- **Intervention Image** - Image processing and optimization

### Collaboration (Phase 7)

- **Yjs** - Conflict-free replicated data types (CRDTs)
- **Hocuspocus** - WebSocket collaboration server
- **Redis** - Real-time state management

## Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   React/Tiptap  │◄──►│   Laravel API   │◄──►│   PostgreSQL    │
│   Frontend      │    │   Backend       │    │   Database      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │              ┌─────────────────┐              │
         └──────────────►│  Hocuspocus     │◄─────────────┘
                        │  Collab Server  │
                        └─────────────────┘
                                 │
                        ┌─────────────────┐
                        │     Redis       │
                        │   State Store   │
                        └─────────────────┘
```

## Implementation Phases

### Phase 1: Core Tiptap Setup and Basic Editing (1-2 weeks)

#### 1.1 Dependencies Installation

```bash
# Core Tiptap packages
npm install @tiptap/react @tiptap/pm @tiptap/starter-kit
npm install @tiptap/extension-placeholder @tiptap/extension-focus
npm install @tiptap/extension-typography @tiptap/extension-text-style
npm install @tiptap/extension-color @tiptap/extension-text-align
npm install @tiptap/extension-link @tiptap/extension-list-item
```

#### 1.2 Core Components

**TiptapEditor Component**

- Location: `resources/js/components/editor/TiptapEditor.tsx`
- Features: Basic text formatting, headings, lists, links
- Props: `content`, `onUpdate`, `editable`, `placeholder`, `className`

**EditorToolbar Component**

- Location: `resources/js/components/editor/EditorToolbar.tsx`
- Features: Bold, italic, headings, lists, alignment controls
- Integration: Works with Tiptap editor instance

#### 1.3 Laravel Backend

**GameContentController**

- Location: `app/Http/Controllers/GameContentController.php`
- Methods:
    - `canEdit(Game $game): bool` - Permission checking
    - `updateContent(Game $game, Request $request)` - Content updates

**API Routes**

```php
Route::middleware(['auth:sanctum', CanEditGame::class])->group(function () {
    Route::put('/games/{game}/content', [GameContentController::class, 'updateContent']);
});
```

#### 1.4 Deliverables

- ✅ Basic rich text editing interface
- ✅ Permission-based editing controls
- ✅ Auto-save functionality
- ✅ Responsive design integration

---

### Phase 2: Image Management System (1-2 weeks)

#### 2.1 Advanced Image Extension

**AdvancedImage Extension**

- Location: `resources/js/components/editor/extensions/AdvancedImage.ts`
- Features:
    - Drag & drop upload
    - Resize handles with constraints
    - Alignment controls (left, center, right, full-width)
    - Caption support
    - Responsive image sizes

**ImageComponent**

- Location: `resources/js/components/editor/components/ImageComponent.tsx`
- Features:
    - React node view for images
    - Interactive resize controls
    - Alignment toolbar
    - Caption editing
    - Deletion functionality

#### 2.2 Image Upload Service

**Frontend Service**

- Location: `resources/js/services/ImageUploadService.ts`
- Methods:
    - `uploadImage(file: File, gameId: number)` - Multi-size upload
    - `deleteImage(imageUrl: string, gameId: number)` - Cleanup

**Backend Controller**

- Location: `app/Http/Controllers/GameImageController.php`
- Features:
    - Multiple image size generation (thumbnail, medium, large, original)
    - Image optimization and compression
    - Storage organization by game ID
    - Permission validation

#### 2.3 Image Processing Pipeline

**Size Variants**

- **Thumbnail**: 150x150px (cropped)
- **Medium**: 400x300px (fitted)
- **Large**: 800x600px (fitted)
- **Original**: Preserved as uploaded

**Storage Structure**

```
storage/
└── game-images/
    └── {game_id}/
        ├── original_{filename}
        ├── thumbnail_{filename}
        ├── medium_{filename}
        └── large_{filename}
```

#### 2.4 Deliverables

- ✅ Drag & drop image upload
- ✅ Image resize and positioning controls
- ✅ Multi-size image generation
- ✅ Caption management
- ✅ Image deletion functionality

---

### Phase 3: Embed Extensions (1-2 weeks)

#### 3.1 YouTube Embed Extension

**YouTubeEmbed Extension**

- Location: `resources/js/components/editor/extensions/YouTubeEmbed.ts`
- Features:
    - URL parsing and validation
    - Responsive iframe embedding
    - Size and alignment controls
    - Privacy-enhanced mode support

**YouTubeComponent**

- Location: `resources/js/components/editor/components/YouTubeComponent.tsx`
- Features:
    - Resizable video player
    - Alignment controls
    - Preview thumbnail
    - Error handling for invalid URLs

#### 3.2 Twitter Embed Extension

**TwitterEmbed Extension**

- Location: `resources/js/components/editor/extensions/TwitterEmbed.ts`
- Features:
    - Tweet ID extraction
    - Twitter API integration
    - Theme support (light/dark)
    - Fallback for deleted tweets

#### 3.3 Bluesky Embed Extension

**BlueskyEmbed Extension**

- Location: `resources/js/components/editor/extensions/BlueskyEmbed.ts`
- Features:
    - Official Bluesky embed widget support
    - Post URL and AT URI parsing
    - Dynamic script loading
    - Theme support (light/dark/system)
    - Fallback iframe embedding
    - Responsive design
    - Error handling

#### 3.4 Embed Toolbar

**EmbedToolbar Component**

- Location: `resources/js/components/editor/EmbedToolbar.tsx`
- Features:
    - Modal dialogs for URL input
    - Platform detection
    - Preview functionality
    - Error validation

#### 3.5 Deliverables

- ✅ YouTube video embedding with resize
- ✅ Twitter tweet embedding
- ✅ Bluesky post embedding
- ✅ Universal embed toolbar
- ✅ URL validation and error handling

---

### Phase 4: Advanced Layout and Styling (1 week)

#### 4.1 Layout Extensions

**Columns Extension**

- Two-column and three-column layout support
- Responsive column stacking
- Content distribution controls

**Advanced Typography**

- Code blocks with syntax highlighting
- Blockquotes with attribution
- Highlight and annotation tools
- Custom text colors and backgrounds

#### 4.2 Table Support

**Table Extension Integration**

- `@tiptap/extension-table` implementation
- Row and column management
- Header row support
- Cell merging capabilities

#### 4.3 Styling System

**CSS Framework**

- Location: `resources/css/editor.css`
- Features:
    - Dark mode support
    - Responsive breakpoints
    - Animation transitions
    - Focus and selection states

#### 4.4 Deliverables

- ✅ Multi-column layouts
- ✅ Advanced typography options
- ✅ Table creation and editing
- ✅ Comprehensive styling system

---

### Phase 5: Permission System and UI Integration (1 week)

#### 5.1 Permission Architecture

**Authorization Logic**

- Game ownership verification via `getOwnedGames()`
- Section-level permissions (description vs. full_description)
- Role-based access control foundation

**Middleware Implementation**

- Location: `app/Http/Middleware/CanEditGame.php`
- Features:
    - Game ownership validation
    - User authentication checks
    - API endpoint protection

#### 5.2 UI Integration

**Game Show Page Enhancement**

- Location: `resources/js/pages/games/show.tsx`
- Features:
    - Seamless edit mode toggling
    - Section-specific editing
    - Save/cancel functionality
    - Loading states and feedback

**Edit Mode Interface**

- Visual indicators for editable sections
- Toolbar positioning and responsiveness
- Mobile-friendly controls
- Keyboard shortcuts

#### 5.3 State Management

**Editing State**

- Current editing section tracking
- Unsaved changes detection
- Conflict resolution for concurrent edits
- Local storage backup

#### 5.4 Deliverables

- ✅ Complete permission system
- ✅ Seamless UI integration
- ✅ Mobile-responsive editing
- ✅ State management implementation

---

### Phase 6: Performance and Polish (1 week)

#### 6.1 Auto-save Implementation

**useAutoSave Hook**

- Location: `resources/js/hooks/useAutoSave.ts`
- Features:
    - Debounced saving (2-second delay)
    - Error handling and retry logic
    - Network status awareness
    - Visual save indicators

#### 6.2 Performance Optimizations

**Component Optimization**

- React.memo for expensive components
- Extension memoization
- Lazy loading for large documents
- Bundle size optimization

**Backend Optimization**

- Response caching
- Image CDN integration
- Database query optimization
- API rate limiting

#### 6.3 Error Recovery

**ErrorRecoveryService**

- Location: `resources/js/services/ErrorRecoveryService.ts`
- Features:
    - Local storage backup
    - Auto-recovery on page reload
    - Conflict resolution
    - Data integrity checks

#### 6.4 Accessibility

**A11y Implementation**

- Keyboard navigation support
- Screen reader compatibility
- High contrast mode
- Focus management

#### 6.5 Deliverables

- ✅ Auto-save functionality
- ✅ Performance optimizations
- ✅ Error recovery system
- ✅ Accessibility compliance

---

### Phase 7: Collaborative Editing (2-3 weeks)

#### 7.1 Collaboration Infrastructure

**Hocuspocus Server Setup**

- Location: `collaboration-server/server.js`
- Features:
    - WebSocket connection management
    - Authentication integration
    - Document persistence
    - Redis state synchronization

**Docker Configuration**

```yaml
services:
  hocuspocus:
    build: ./collaboration-server
    ports:
      - "1234:1234"
    environment:
      - LARAVEL_API_URL=http://app:80
      - REDIS_URL=redis://redis:6379
```

#### 7.2 Real-time Collaboration

**CollaborativeTiptapEditor**

- Location: `resources/js/components/editor/CollaborativeTiptapEditor.tsx`
- Features:
    - Yjs document synchronization
    - User presence indicators
    - Conflict-free editing
    - Offline support with sync

**Collaboration Extensions**

- `@tiptap/extension-collaboration` - Document sync
- `@tiptap/extension-collaboration-cursor` - User cursors
- `y-indexeddb` - Offline persistence

#### 7.3 User Presence

**ActiveUsers Component**

- Location: `resources/js/components/editor/ActiveUsers.tsx`
- Features:
    - Real-time user list
    - Avatar display
    - User color coding
    - Connection status

#### 7.4 Backend Integration

**CollaborationController**

- Location: `app/Http/Controllers/CollaborationController.php`
- Methods:
    - `verifyAccess()` - User authorization
    - `getDocument()` - Document retrieval
    - `storeDocument()` - Document persistence

**Database Schema**

```sql
CREATE TABLE collaboration_documents (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    content LONGTEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 7.5 Deliverables

- ✅ Real-time collaborative editing
- ✅ User presence and cursors
- ✅ Conflict resolution
- ✅ Offline editing support
- ✅ Document persistence

---

## Technical Specifications

### Frontend Architecture

```typescript
interface EditorConfig {
  extensions: Extension[];
  content: string;
  editable: boolean;
  onUpdate: (content: string) => void;
  placeholder?: string;
  collaboration?: {
    enabled: boolean;
    documentId: string;
    user: User;
  };
}

interface User {
  id: number;
  name: string;
  avatar?: string;
  color?: string;
}

interface GameEditPermissions {
  canEdit: boolean;
  sections: string[];
  isOwner: boolean;
}
```

### API Endpoints

```
POST /api/games/{game}/content
PUT  /api/games/{game}/content
GET  /api/games/{game}/permissions

POST /api/games/images/upload
DELETE /api/games/images/{image}

POST /api/collaboration/verify
GET  /api/collaboration/documents/{document}
POST /api/collaboration/documents/{document}
```

### File Structure

```
resources/js/
├── components/
│   └── editor/
│       ├── TiptapEditor.tsx
│       ├── EditorToolbar.tsx
│       ├── EmbedToolbar.tsx
│       ├── CollaborativeTiptapEditor.tsx
│       ├── ActiveUsers.tsx
│       ├── extensions/
│       │   ├── AdvancedImage.ts
│       │   ├── YouTubeEmbed.ts
│       │   ├── TwitterEmbed.ts
│       │   ├── BlueskyEmbed.ts
│       │   └── Columns.ts
│       └── components/
│           ├── ImageComponent.tsx
│           ├── YouTubeComponent.tsx
│           └── EmbedComponent.tsx
├── hooks/
│   ├── useAutoSave.ts
│   └── useCollaboration.ts
└── services/
    ├── ImageUploadService.ts
    ├── ErrorRecoveryService.ts
    └── CollaborationService.ts

app/Http/
├── Controllers/
│   ├── GameContentController.php
│   ├── GameImageController.php
│   └── CollaborationController.php
├── Middleware/
│   └── CanEditGame.php
└── Requests/
    ├── UpdateGameContentRequest.php
    └── UploadImageRequest.php

collaboration-server/
├── server.js
├── package.json
├── Dockerfile
└── docker-compose.yml
```

## Security Considerations

### Authentication & Authorization

- JWT token validation for collaboration server
- Game ownership verification via itch.io URL matching
- Section-level permission checks
- Rate limiting on API endpoints

### Content Security

- Input sanitization for rich text content
- XSS prevention in embed content
- Image upload validation and scanning
- Content-Security-Policy headers

### Data Protection

- HTTPS enforcement for all communications
- WebSocket connection encryption
- User data anonymization in logs
- GDPR compliance for stored content

## Testing Strategy

### Unit Tests

- Extension functionality testing
- Component rendering tests
- Service layer validation
- Permission logic verification

### Integration Tests

- API endpoint testing
- Database interaction tests
- Image upload/processing tests
- Collaboration synchronization tests

### End-to-End Tests

- Complete editing workflow
- Multi-user collaboration scenarios
- Mobile responsiveness
- Accessibility compliance

## Deployment Architecture

### Production Environment

```yaml
services:
  app:
    build: .
    environment:
      - COLLABORATION_SERVER_URL=wss://collab.fvn.li
  
  hocuspocus:
    build: ./collaboration-server
    ports:
      - "1234:1234"
    environment:
      - LARAVEL_API_URL=https://fvn.li
      - REDIS_URL=redis://redis:6379
  
  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
  
  nginx:
    image: nginx:alpine
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
    ports:
      - "80:80"
      - "443:443"
```

### Domain Configuration

- Main app: `https://fvn.li`
- Collaboration: `wss://collab.fvn.li`
- Media CDN: `https://cdn.fvn.li`

## Monitoring & Analytics

### Performance Metrics

- Editor load time
- Auto-save response time
- Image upload duration
- Collaboration latency

### User Analytics

- Feature usage statistics
- Error rate tracking
- User engagement metrics
- Mobile vs desktop usage

### System Health

- Server resource utilization
- WebSocket connection monitoring
- Database query performance
- CDN cache hit rates

## Migration Strategy

### Data Migration

- Existing game descriptions to new format
- HTML content cleaning and validation
- Image asset organization
- User permission assignment

### Rollout Plan

1. **Beta Testing** (1 week) - Selected game owners
2. **Gradual Rollout** (2 weeks) - 25% → 50% → 100%
3. **Feature Announcement** - Documentation and tutorials
4. **Feedback Collection** - User surveys and analytics review

## Success Metrics

### User Engagement

- Percentage of game owners using rich editing features
- Average time spent in edit mode
- Number of images and embeds added per game
- Collaboration session frequency and duration

### Technical Performance

- 95th percentile load time < 2 seconds
- Auto-save success rate > 99.5%
- Image upload success rate > 99%
- Collaboration sync latency < 100ms

### Business Impact

- Increased game page engagement
- Higher quality game descriptions
- Improved SEO performance
- Enhanced platform stickiness

---

## Timeline Summary

| Phase     | Duration       | Key Features                      |
|-----------|----------------|-----------------------------------|
| Phase 1   | 1-2 weeks      | Core Tiptap setup, basic editing  |
| Phase 2   | 1-2 weeks      | Image management, upload, resize  |
| Phase 3   | 1-2 weeks      | YouTube, Twitter, Bluesky embeds  |
| Phase 4   | 1 week         | Advanced layout and styling       |
| Phase 5   | 1 week         | Permission system, UI integration |
| Phase 6   | 1 week         | Performance, auto-save, polish    |
| Phase 7   | 2-3 weeks      | Collaborative editing             |
| **Total** | **8-12 weeks** | **Complete implementation**       |

## Conclusion

This implementation provides a comprehensive rich text editing solution that transforms static game pages into dynamic,
collaborative content creation environments. The phased approach ensures a solid foundation with single-user features
before adding the complexity of real-time collaboration.

The architecture is designed for scalability, maintainability, and extensibility, allowing for future enhancements while
maintaining excellent performance and user experience across all devices and network conditions.
